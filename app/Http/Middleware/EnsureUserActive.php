<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ($user->status ?? 'active') === 'banned') {
            return response()->json(['message' => 'Votre compte a été suspendu.'], 403);
        }
        return $next($request);
    }
}
