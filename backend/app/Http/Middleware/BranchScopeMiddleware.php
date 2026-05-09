<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'superadmin' && !$user->branch_id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke cabang manapun.'
            ], 403);
        }

        // If request has branch_id, ensure user has access to it (unless superadmin)
        if ($request->has('branch_id') && $user->role !== 'superadmin') {
            if ($request->branch_id != $user->branch_id) {
                return response()->json([
                    'message' => 'Akses ke cabang ini ditolak.'
                ], 403);
            }
        }

        return $next($request);
    }
}
