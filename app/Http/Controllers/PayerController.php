<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->receivers()->orderBy('name')->orderBy('id')->get(['id', 'name']));
    }
}
