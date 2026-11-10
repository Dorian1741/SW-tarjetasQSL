<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if ($user) {
            $user = $user->fresh();
            $fingerprint = $request->session()->get('password_fingerprint');
            if ($user->status !== 'active' || ($fingerprint && ! hash_equals($fingerprint, hash('sha256', $user->password)))) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                abort(401, 'La sesión fue invalidada. Inicia sesión nuevamente.');
            }
            $request->session()->put('password_fingerprint', hash('sha256', $user->password));
        }

        return $next($request);
    }
}
