<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->incomes()
            ->with('tags')->orderByDesc('date')->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, int $income): JsonResponse
    {
        $record = $request->user()->incomes()->findOrFail($income);

        return $this->save($request, $record);
    }

    private function save(Request $request, ?Income $record = null): JsonResponse
    {
        $data = $this->validatedData($request);
        $income = DB::transaction(function () use ($request, $record, $data): Income {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $income = $record ?? new Income;
            $income->fill([
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'date' => $data['date'],
            ]);
            $user->incomes()->save($income);

            if (array_key_exists('tags', $data)) {
                $tagIds = [];
                foreach ($data['tags'] as $name) {
                    $tag = $user->tags()->firstOrCreate(['name' => $name]);
                    $tagIds[$tag->id] = ['user_id' => $user->id];
                }
                $income->tags()->sync($tagIds);
            }

            return $income->load('tags');
        });

        return response()->json($income, $record ? 200 : 201);
    }

    public function destroy(Request $request, int $income): Response
    {
        $record = $request->user()->incomes()->findOrFail($income);
        $record->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'date' => ['required', 'date_format:Y-m-d'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'max:255'],
        ]);
    }
}
