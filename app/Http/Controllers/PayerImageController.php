<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PayerImageController extends Controller
{
    public function store(Request $request, int $payer): JsonResponse
    {
        $record = $request->user()->receivers()->findOrFail($payer);
        $request->validate([
            'image' => ['bail', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ]);

        $image = $request->file('image');
        $media = $record->addMedia($image)
            ->usingFileName(Str::uuid().'.'.$image->extension())
            ->toMediaCollection('image');

        return response()->json(['image_url' => $media->getUrl('avatar')]);
    }

    public function destroy(Request $request, int $payer): Response
    {
        $request->user()->receivers()->findOrFail($payer)->clearMediaCollection('image');

        return response()->noContent();
    }
}
