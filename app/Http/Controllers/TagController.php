<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->tags()->orderBy('name')->orderBy('id')->get(['id', 'name', 'icon', 'color']));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, int $tag): JsonResponse
    {
        return $this->save($request, $tag);
    }

    private function save(Request $request, ?int $tag = null): JsonResponse
    {
        $record = DB::transaction(function () use ($request, $tag): Tag {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $record = $tag === null ? new Tag : $user->tags()->findOrFail($tag);
            $data = $request->validate([
                'name' => ['bail', 'required', 'string', 'max:255', Rule::unique(Tag::class, 'name')->where('user_id', $user->id)->ignore($record->id)],
                'icon' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(config('tag-icons')))],
                'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ]);
            $record->fill($data);
            $user->tags()->save($record);

            return $record->refresh();
        });

        return response()->json($record, $tag === null ? 201 : 200);
    }

    public function destroy(Request $request, int $tag): Response
    {
        DB::transaction(function () use ($request, $tag): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $user->tags()->findOrFail($tag)->delete();
        });

        return response()->noContent();
    }
}
