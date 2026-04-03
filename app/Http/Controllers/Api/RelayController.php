<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RelayAutoConfig;
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

            RelayAutoConfig::where('relay_id', $id)
                ->update(['last_auto_state' => (bool) $state]);

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

            RelayAutoConfig::where('auto_enabled', true)
                ->update(['last_auto_state' => (bool) $state]);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'ESP relay tidak merespons'], 502);
        }
    }

    public function autoConfig(): JsonResponse
    {
        return response()->json(RelayAutoConfig::all());
    }

    public function updateAutoConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relay_id' => 'required|integer|between:0,3',
            'auto_enabled' => 'required|boolean',
            'sensor_type' => 'required|in:light,temperature',
            'condition' => 'required|in:below,above',
            'threshold_on' => 'required|numeric|min:0',
            'threshold_off' => 'required|numeric|min:0',
        ]);

        if ($validated['condition'] === 'below' && $validated['threshold_off'] <= $validated['threshold_on']) {
            return response()->json(['message' => 'Untuk kondisi "di bawah", threshold OFF harus lebih besar dari threshold ON'], 422);
        }

        if ($validated['condition'] === 'above' && $validated['threshold_off'] >= $validated['threshold_on']) {
            return response()->json(['message' => 'Untuk kondisi "di atas", threshold OFF harus lebih kecil dari threshold ON'], 422);
        }

        $config = RelayAutoConfig::findOrFail($validated['relay_id']);
        $config->update($validated);

        return response()->json($config);
    }
}
