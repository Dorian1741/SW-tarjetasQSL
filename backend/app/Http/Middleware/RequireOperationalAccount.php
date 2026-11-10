<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOperationalAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->status === 'active' && $user->hasVerifiedEmail(), 403, 'Verifica tu correo para continuar.');
        abort_unless($user->hasEnabledTwoFactorAuthentication() && hash_equals(hash('sha256', $user->two_factor_secret), $request->session()->get('two_factor_proof', '')), 403, 'Configura y confirma el segundo factor para continuar.');

        return $next($request);
    }
}
