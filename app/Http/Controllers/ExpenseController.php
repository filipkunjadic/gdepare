<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->expenses()
            ->with(['receiver', 'tags'])
            ->orderByDesc('date')->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, int $expense): JsonResponse
    {
        $record = $request->user()->expenses()->findOrFail($expense);

        return $this->save($request, $record);
    }

    private function save(Request $request, ?Expense $record = null): JsonResponse
    {
        $data = $this->validatedData($request, $record);
        $expense = DB::transaction(function () use ($request, $data, $record): Expense {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $receiver = $user->receivers()->firstOrCreate(['name' => $data['receiver']]);
            $expense = $record ?? new Expense;
            $expense->fill([
                'description' => $data['description'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'date' => $data['date'],
                'payment_method' => $data['payment_method'],
                'category' => $data['category'] ?? $expense->category ?? 'expense',
            ]);
            if ($expense->category !== 'savings') {
                $expense->foreign_amount = null;
                $expense->foreign_currency = null;
            } elseif (array_key_exists('foreign_amount', $data) || array_key_exists('foreign_currency', $data)) {
                $expense->foreign_amount = $data['foreign_amount'] ?? null;
                $expense->foreign_currency = $data['foreign_currency'] ?? null;
            }
            $expense->receiver()->associate($receiver);
            $user->expenses()->save($expense);

            $tagIds = [];
            foreach ($data['tags'] ?? [] as $name) {
                $tag = $user->tags()->firstOrCreate(['name' => $name]);
                $tagIds[$tag->id] = ['user_id' => $user->id];
            }
            $expense->tags()->sync($tagIds);

            return $expense->load(['receiver', 'tags']);
        });

        return response()->json($expense, $record ? 200 : 201);
    }

    public function destroy(Request $request, int $expense): Response
    {
        $record = $request->user()->expenses()->findOrFail($expense);
        $record->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Expense $record = null): array
    {
        $isSavings = $request->input('category', $record->category ?? 'expense') === 'savings';

        return $request->validate([
            'category' => ['sometimes', 'required', Rule::in(['expense', 'savings'])],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'currency' => ['required', 'string', $isSavings ? Rule::in(['RSD']) : 'regex:/^[A-Z]{3}$/'],
            'foreign_amount' => [Rule::prohibitedIf(! $isSavings), 'nullable', 'required_with:foreign_currency', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'foreign_currency' => [Rule::prohibitedIf(! $isSavings), 'nullable', 'required_with:foreign_amount', Rule::in(['EUR'])],
            'date' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['required', Rule::in(['card', 'cash', 'bank_transfer', 'unspecified'])],
            'receiver' => ['required', 'string', 'max:255'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'max:255'],
        ]);

    }
}
