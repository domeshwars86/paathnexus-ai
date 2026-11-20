<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if ($request->user()->account_status !== 'active') {
            $request->user()->currentAccessToken()?->delete();

            return response()->json(['message' => 'This account is not active.'], 403);
        }

        return $next($request);
    }
}
