<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(config('auth.require_verified_email') && ! $request->user()?->hasVerifiedEmail(), 403, 'Verify your email before using this workspace.');
        return $next($request);
    }
}
