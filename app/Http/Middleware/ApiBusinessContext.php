<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiBusinessContext
{
    /**
     * Handle an incoming request.
     * Sets the business_id in the request for API consumers.
     * Does NOT use session (API routes don't have session middleware).
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            // Store business_id in request attributes for easy access
            // This replaces session-based business_id for API routes
            $request->attributes->set('business_id', $user->business_id);
        }

        return $next($request);
    }
}
