<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->admin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            abort(403, 'No tenés permisos para acceder al panel de administración.');
        }

        return $next($request);
    }
}
