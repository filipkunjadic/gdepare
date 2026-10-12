<?php

namespace App\Http\Controllers;

use App\Models\Receiver;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->receivers()->select(['id', 'name'])
            ->with(['media', 'defaultTags'])->withCount('expenses')->orderBy('name')->orderBy('id')->get()
            ->map(fn (Receiver $payer): array => [
                'id' => $payer->id,
                'name' => $payer->name,
                'image_url' => $payer->getFirstMediaUrl('image', 'avatar') ?: null,
                'default_tags' => $payer->defaultTags,
                'transactions_count' => $payer->expenses_count,
            ]));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, int $payer): JsonResponse
    {
        return $this->save($request, $payer);
    }

    private function save(Request $request, ?int $payer = null): JsonResponse
    {
        $record = DB::transaction(function () use ($request, $payer): Receiver {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $record = $payer === null ? new Receiver : $user->receivers()->findOrFail($payer);
            $data = $request->validate([
                'name' => ['bail', 'required', 'string', 'max:255', Rule::unique(Receiver::class, 'name')->where('user_id', $user->id)->ignore($record->id)],
                'image' => ['bail', 'sometimes', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
                'remove_image' => ['sometimes', 'boolean'],
                'default_tags' => ['sometimes', 'nullable', 'array', 'max:20'],
                'default_tags.*' => ['required', 'string', 'max:255'],
            ]);

            $record->fill(['name' => $data['name']]);
            $user->receivers()->save($record);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $record->addMedia($image)
                    ->usingFileName(Str::uuid().'.'.$image->extension())
                    ->toMediaCollection('image');
            } elseif ($request->boolean('remove_image')) {
                $record->clearMediaCollection('image');
            }

            if (array_key_exists('default_tags', $data)) {
                $tagIds = [];
                foreach ($data['default_tags'] ?? [] as $name) {
                    $tag = $user->tags()->firstOrCreate(['name' => $name]);
                    $tagIds[$tag->id] = ['user_id' => $user->id];
                }
                $record->defaultTags()->sync($tagIds);
            }

            return $record->refresh()->load(['defaultTags', 'media'])->loadCount('expenses');
        });

        return response()->json([
            'id' => $record->id,
            'name' => $record->name,
            'image_url' => $record->getFirstMediaUrl('image', 'avatar') ?: null,
            'default_tags' => $record->defaultTags,
            'transactions_count' => $record->expenses_count,
        ], $payer === null ? 201 : 200);
    }

    public function destroy(Request $request, int $payer): Response
    {
        DB::transaction(function () use ($request, $payer): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $record = $user->receivers()->findOrFail($payer);

            if ($user->expenses()->whereBelongsTo($record, 'receiver')->exists()) {
                throw ValidationException::withMessages([
                    'payer' => 'This payer has existing transactions. Assign them to another payer before deleting it.',
                ]);
            }

            $record->delete();
        });

        return response()->noContent();
    }
}
