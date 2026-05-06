<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeiraNotSyncing
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $feira = $request->route('feira');

        if ($feira && $feira instanceof \App\Models\Feira && $feira->is_sincronizando) {
            return response()->json([
                'error' => 'Locked',
                'message' => 'Esta feira está a ser sincronizada. Algumas ações estão bloqueadas.'
            ], 423);
        }

        return $next($request);
    }
}
