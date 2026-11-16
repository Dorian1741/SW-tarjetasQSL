<?php

namespace App\Http\Middleware;

use App\Models\IntegrationCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->isSecure() || app()->environment(['local', 'testing']), 403, 'La integración requiere HTTPS.');
        // No aceptar cookies como credenciales de servidor; solo el Bearer delegado explícitamente.
        $token = $request->bearerToken();
        abort_unless(is_string($token) && preg_match('/^nexus_([0-9a-f-]{36})\.([A-Za-z0-9]{64})$/D', $token, $parts), 401, 'Credencial de integración inválida.');
        abort_unless(Str::isUuid($parts[1]), 401, 'Credencial de integración inválida.');
        $credential = IntegrationCredential::with('user', 'callsign')->find($parts[1]);
        abort_unless($credential && hash_equals($credential->token_hash, hash('sha256', $token)) && $credential->usable(), 401, 'Credencial revocada, expirada o no válida.');
        $key = 'integration:'.$credential->id;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['message' => 'Límite de integración alcanzado.'], 429, ['Retry-After' => max(1, RateLimiter::availableIn($key))]);
        }
        RateLimiter::hit($key, 60);
        abort_if($request->isMethod('POST') && ! $request->isJson(), 415, 'Envía Content-Type: application/json.');
        $request->attributes->set('integration', $credential);
        $request->setUserResolver(fn () => $credential->user);
        IntegrationCredential::whereKey($credential->id)->where(fn ($q) => $q->whereNull('last_used_at')->orWhere('last_used_at', '<', now()->subMinutes(5)))->update(['last_used_at' => now()]);
        $response = $next($request);
        $response->headers->set('Cache-Control','private, no-store');

        return $response;
    }
}
