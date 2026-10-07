<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Financial report - {{ $period }}</title>
    <style>
        @page { margin: 30pt 34pt 44pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #1e293b; }
        h1 { font-size: 23pt; margin: 3pt 0; letter-spacing: -0.5pt; }
        h2 { font-size: 11pt; margin: 16pt 0 6pt; page-break-after: avoid; }
        h3 { font-size: 8pt; margin: 0 0 6pt; }
        p { margin: 0 0 3pt; }
        .eyebrow { color: #6366f1; font-size: 7pt; letter-spacing: 1.4pt; }
        .muted, .currency { color: #64748b; font-size: 7pt; }
        .header { border-bottom: 2pt solid #4338ca; padding-bottom: 9pt; margin-bottom: 12pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #f1f5f9; color: #64748b; font-size: 7pt; text-align: left; padding: 5pt; }
        td { border-bottom: 0.5pt solid #e2e8f0; padding: 5pt; vertical-align: top; word-wrap: break-word; }
        .number { text-align: right; }
        .summary .number { font-size: 8pt; }
        .summary td { padding: 8pt 5pt; }
        .balance { font-weight: bold; color: #4338ca; background: #eef2ff; }
        .income { color: #047857; }
        .expense { color: #dc2626; }
        .savings { color: #7c3aed; }
        .note { margin-top: 5pt; color: #64748b; font-size: 6.5pt; }
        .empty { padding: 12pt 0; color: #64748b; }
        .metrics td { background: #f8fafc; border: 3pt solid white; padding: 7pt; }
        .metric { font-size: 12pt; font-weight: bold; margin-top: 3pt; }
        .metric-long { font-size: 8pt; }
        .rank-value { text-align: right; margin-top: 2pt; white-space: nowrap; }
        .breakdowns > tbody > tr > td { width: 33.33%; border: none; padding: 8pt 5pt 0; }
        .rankings td { padding: 4pt 0; font-size: 7pt; }
        .rankings .number { width: 49%; padding-left: 4pt; }
        .track { height: 3pt; background: #f1f5f9; margin: 3pt 0 1pt; }
        .bar { height: 3pt; background: #818cf8; }
        .income-bar { background: #34d399; }
        .ledger tbody tr:nth-child(even) { background: #f8fafc; }
        .ledger td { padding: 2pt 5pt; font-size: 7.5pt; line-height: 1.1; page-break-inside: avoid; }
        .detail { font-size: 6.5pt; color: #64748b; margin: 1pt 0 0; }
        .amount { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <p class="eyebrow">FINANCIAL REPORT</p>
        <h1>{{ $period }}</h1>
        <p class="muted">{{ $owner }} &nbsp; / &nbsp; Generated {{ $generated }}</p>
    </div>
    @if (count($totals))
        <h2>At a glance</h2>
        <table class="summary">
            <thead><tr><th width="9%">Currency</th><th class="number">Income</th><th class="number">Expenses</th><th class="number">Savings</th><th class="number">Available</th></tr></thead>
            <tbody>
                @foreach ($totals as $currency => $total)
                    <tr>
                        <td>{{ $currency }}</td>
                        <td class="number income">{{ \App\FinancialReport::formatAmount($total['income']) }}</td>
                        <td class="number expense">{{ \App\FinancialReport::formatAmount($total['expenses']) }}</td>
                        <td class="number savings">{{ \App\FinancialReport::formatAmount($total['savings']) }}</td>
                        <td class="number balance">{{ \App\FinancialReport::formatAmount($total['balance']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="note">Available = income - expenses - savings. Period totals only; no opening balance. Currencies are not combined.</p>
        @foreach ($totals as $currency => $total)
            @if ($total['savings_eur'] !== '0.00')
                <p class="note">Savings also recorded as {{ \App\FinancialReport::formatAmount($total['savings_eur']) }} EUR; deducted only once at the {{ $currency }} value.</p>
            @endif
        @endforeach
        @foreach ($statistics as $currency => $stats)
            <h2>Statistics <span class="muted">/ {{ $currency }}</span></h2>
            <table class="metrics"><tr>
                <td><p class="muted">Transactions</p><p class="metric">{{ array_sum($stats['counts']) }}</p><p class="note">{{ $stats['counts']['income'] }} income / {{ $stats['counts']['expense'] }} expenses / {{ $stats['counts']['savings'] }} savings</p></td>
                <td><p class="muted">Average expense</p><p class="metric {{ strlen($stats['average_expense']) > 10 ? 'metric-long' : '' }}">{{ \App\FinancialReport::formatAmount($stats['average_expense']) }}</p><p class="note">{{ $currency }} per expense</p></td>
                <td><p class="muted">Income spent</p><p class="metric expense">{{ $stats['spending_rate'] === null ? 'N/A' : $stats['spending_rate'].'%' }}</p><p class="note">Expenses / income</p></td>
                <td><p class="muted">Income saved</p><p class="metric savings">{{ $stats['savings_rate'] === null ? 'N/A' : $stats['savings_rate'].'%' }}</p><p class="note">Savings / income</p></td>
            </tr></table>
            <table class="breakdowns"><tr>
                @foreach (['payers' => 'Expenses by payer', 'expense_tags' => 'Expenses by tag', 'income_tags' => 'Income by tag'] as $key => $title)
                    <td>
                        <h3>{{ $title }}</h3>
                        <table class="rankings">
                            @forelse ($stats['groups'][$key] as $group)
                                <tr>
                                    <td>{{ $group['label'] }}<p class="rank-value">{{ \App\FinancialReport::formatAmount($group['amount']) }} <span class="muted"> / {{ $group['percentage'] === null ? 'N/A' : $group['percentage'] . '%' }}</span></p><div class="track"><div class="bar {{ $key === 'income_tags' ? 'income-bar' : '' }}" style="width: {{ min(100, max(0, (float) ($group['percentage'] ?? 0))) }}%"></div></div></td>
                                </tr>
                            @empty
                                <tr><td class="muted">No transactions</td></tr>
                            @endforelse
                        </table>
                    </td>
                @endforeach
            </tr></table>
            <p class="note">Top 5 in each group, in {{ $currency }}. Shares use total expenses or income, including untagged records. Tags can overlap. Savings excluded from expense statistics. Rates are N/A when income is zero.</p>
        @endforeach
    @else
        <p class="empty">No transactions recorded for this period.</p>
    @endif
    @if (count($transactions))
        <h2>Transaction details <span class="muted">/ {{ count($transactions) }} records</span></h2>
        <table class="ledger">
            <thead><tr><th width="14%">Date</th><th width="12%">Type</th><th width="47%">Details</th><th width="27%" class="number">Amount</th></tr></thead>
            <tbody>
                @foreach ($transactions as $transaction)
                    <tr>
                        <td>{{ \Carbon\CarbonImmutable::parse($transaction['date'])->format($dateFormat) }}</td>
                        <td class="{{ $transaction['kind'] }}">{{ ucfirst($transaction['kind']) }}</td>
                        <td>
                            {{ $transaction['description'] }}
                            @if (($transaction['payer'] && $transaction['payer'] !== $transaction['description']) || $transaction['tags'])
                                <span class="detail"> / {{ implode(' / ', array_filter([$transaction['payer'] !== $transaction['description'] ? $transaction['payer'] : '', $transaction['tags']])) }}</span>
                            @endif
                        </td>
                        <td class="number">
                            <span class="amount {{ $transaction['kind'] }}">{{ \App\FinancialReport::formatAmount($transaction['amount']) }}</span> <span class="currency">{{ $transaction['currency'] }}</span>
                            @if ($transaction['foreign_amount'] !== null)
                                <p class="detail">{{ \App\FinancialReport::formatAmount($transaction['foreign_amount']) }} {{ $transaction['foreign_currency'] }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
