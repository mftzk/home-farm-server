<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RelayController extends Controller
{
    private function espUrl(string $path): string
    {
        return 'http://' . config('esp.relay_ip') . $path;
    }

    public function status(): JsonResponse
    {
        try {
            $response = Http::timeout(config('esp.timeout'))
                ->get($this->espUrl('/status'));

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'ESP relay tidak merespons'], 502);
        }
    }

    public function toggle(Request $request): JsonResponse
    {
        $id = $request->integer('id');
        $state = $request->integer('state');

        if ($id < 0 || $id > 3 || !in_array($state, [0, 1])) {
            return response()->json(['error' => 'Parameter tidak valid'], 422);
        }

        try {
            $response = Http::timeout(config('esp.timeout'))
                ->get($this->espUrl('/relay'), ['id' => $id, 'state' => $state]);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'ESP relay tidak merespons'], 502);
        }
    }

    public function all(Request $request): JsonResponse
    {
        $state = $request->integer('state');

        if (!in_array($state, [0, 1])) {
            return response()->json(['error' => 'Parameter tidak valid'], 422);
        }

        try {
            $response = Http::timeout(config('esp.timeout'))
                ->get($this->espUrl('/all'), ['state' => $state]);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'ESP relay tidak merespons'], 502);
        }
    }
}
