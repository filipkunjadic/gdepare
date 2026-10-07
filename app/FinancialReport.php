<?php

namespace App;

use App\Models\Expense;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

class FinancialReport
{
    /**
     * @return array{
     *     statistics: array<string, array{counts: array<string, int>, average_expense: string, spending_rate: ?string, savings_rate: ?string, groups: array<string, list<array{label: string, amount: string, percentage: ?string}>>}>,
     *     totals: array<string, array{income: string, expenses: string, savings: string, balance: string, savings_eur: string}>,
     *     transactions: list<array{date: string, kind: string, description: string, payer: string, tags: string, amount: string, currency: string, foreign_amount: ?string, foreign_currency: ?string}>
     * }
     */
    public function data(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $expenses = $user->expenses()->with(['receiver', 'tags'])->where('date', '>=', $start->toDateString())
            ->where('date', '<', $end->toDateString())->orderBy('date')->orderBy('id')->get();
        $incomes = $user->incomes()->with('tags')->where('date', '>=', $start->toDateString())
            ->where('date', '<', $end->toDateString())->orderBy('date')->orderBy('id')->get();
        $totals = [];
        $transactions = [];
        $counts = [];
        $groups = [];

        foreach ([...$incomes, ...$expenses] as $record) {
            $kind = $record instanceof Expense ? ($record->category === 'savings' ? 'savings' : 'expense') : 'income';
            $currency = $record->currency;
            $counts[$currency] ??= ['income' => 0, 'expense' => 0, 'savings' => 0];
            $counts[$currency][$kind]++;
            if ($kind !== 'savings') {
                $dimensions = [$kind.'_tags' => $record->tags->pluck('name', 'id')->all() ?: ['untagged' => 'Untagged']];
                if ($record instanceof Expense) {
                    $dimensions['payers'] = [$record->receiver_id => $record->receiver->name];
                }
                foreach ($dimensions as $dimension => $labels) {
                    foreach ($labels as $id => $label) {
                        $groups[$currency][$dimension][$id] ??= ['label' => $label, 'amount' => '0.00'];
                        $groups[$currency][$dimension][$id]['amount'] = (string) BigDecimal::of($groups[$currency][$dimension][$id]['amount'])->plus($record->amount)->toScale(2);
                    }
                }
            }
            $totals[$currency] ??= ['income' => '0.00', 'expenses' => '0.00', 'savings' => '0.00', 'balance' => '0.00', 'savings_eur' => '0.00'];
            $key = $kind === 'expense' ? 'expenses' : $kind;
            $totals[$currency][$key] = (string) BigDecimal::of($totals[$currency][$key])->plus($record->amount)->toScale(2);
            if ($record instanceof Expense && $kind === 'savings' && $record->foreign_currency === 'EUR' && $record->foreign_amount !== null) {
                $totals[$currency]['savings_eur'] = (string) BigDecimal::of($totals[$currency]['savings_eur'])->plus($record->foreign_amount)->toScale(2);
            }
            $transactions[] = [
                'date' => $record->date->format('Y-m-d'),
                'kind' => $kind,
                'description' => $record->description ?? 'Income',
                'payer' => $record instanceof Expense ? $record->receiver->name : '',
                'tags' => $record->tags->pluck('name')->implode(', '),
                'amount' => $record->amount,
                'currency' => $currency,
                'foreign_amount' => $record instanceof Expense && $kind === 'savings' ? $record->foreign_amount : null,
                'foreign_currency' => $record instanceof Expense && $kind === 'savings' ? $record->foreign_currency : null,
            ];
        }
        foreach ($totals as &$total) {
            $total['balance'] = (string) BigDecimal::of($total['income'])->minus($total['expenses'])->minus($total['savings'])->toScale(2);
        }
        unset($total);
        ksort($totals);
        usort($transactions, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        $statistics = [];
        foreach ($totals as $currency => $total) {
            $rankings = [];
            foreach (['payers', 'expense_tags', 'income_tags'] as $dimension) {
                $rows = array_values($groups[$currency][$dimension] ?? []);
                usort($rows, fn (array $a, array $b): int => BigDecimal::of($b['amount'])->compareTo($a['amount']) ?: strcmp($a['label'], $b['label']));
                $denominator = $dimension === 'income_tags' ? $total['income'] : $total['expenses'];
                $rankings[$dimension] = array_map(fn (array $row): array => [...$row, 'percentage' => self::percentage($row['amount'], $denominator)], array_slice($rows, 0, 5));
            }
            $statistics[$currency] = [
                'counts' => $counts[$currency],
                'average_expense' => $counts[$currency]['expense'] > 0 ? (string) BigDecimal::of($total['expenses'])->dividedBy($counts[$currency]['expense'], 2, RoundingMode::HalfUp) : '0.00',
                'spending_rate' => self::percentage($total['expenses'], $total['income']),
                'savings_rate' => self::percentage($total['savings'], $total['income']),
                'groups' => $rankings,
            ];
        }

        return ['totals' => $totals, 'transactions' => $transactions, 'statistics' => $statistics];
    }

    private static function percentage(string $amount, string $total): ?string
    {
        return BigDecimal::of($total)->isZero() ? null : (string) BigDecimal::of($amount)->multipliedBy(100)->dividedBy($total, 1, RoundingMode::HalfUp);
    }

    public static function formatAmount(string $amount): string
    {
        [$whole, $fraction] = explode('.', (string) BigDecimal::of($amount)->toScale(2));

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$fraction;
    }
}
