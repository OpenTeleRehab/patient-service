<?php

namespace App\Http\Middleware;

use App\Helpers\KeycloakHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Role
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = Auth::user();
        $permitted = false;

        if ($user) {
            if ($user->email === env('KEYCLOAK_BACKEND_USERNAME')) {
                $permitted = !empty(array_intersect($roles, ['internal']));
            } else {
                $permitted = !empty(array_intersect($roles, ['mobile']));
            }
        }

        if (!$permitted) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
