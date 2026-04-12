<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEditMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session('dashboard_mode') !== 'edit') {
            return response()->json([
                'error' => 'Mode edit diperlukan',
                'mode' => 'read',
            ], 403);
        }

        return $next($request);
    }
}
