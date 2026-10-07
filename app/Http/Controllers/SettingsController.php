<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date_format' => ['required', 'string', Rule::in(array_keys(config('date-formats')))],
        ]);
        $user = $request->user();
        $user->update($data);

        return response()->json($user->only(['id', 'name', 'email', 'date_format']));
    }
}
