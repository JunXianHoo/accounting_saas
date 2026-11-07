<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyFromUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401, 'Authentication required.');

        $tenant = $user->tenant;

        abort_unless($tenant !== null, 403, 'Your account has no company.');

        try {
            tenancy()->initialize($tenant);

            return $next($request);
        } finally {
            tenancy()->end();
        }
    }
}
