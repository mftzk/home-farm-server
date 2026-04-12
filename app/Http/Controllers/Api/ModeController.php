<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModeController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'mode' => session('dashboard_mode', 'read'),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string|digits:6',
        ]);

        $expected = config('dashboard.pin');

        if (!hash_equals((string) $expected, $request->input('pin'))) {
            return response()->json([
                'error' => 'PIN salah',
            ], 403);
        }

        session(['dashboard_mode' => 'edit']);

        return response()->json([
            'mode' => 'edit',
        ]);
    }

    public function lock(): JsonResponse
    {
        session(['dashboard_mode' => 'read']);

        return response()->json([
            'mode' => 'read',
        ]);
    }
}
