<?php

namespace App\Services\Callbook;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use SimpleXMLElement;

class CallbookTransport
{
    public function configured(string $provider): bool
    {
        return config('callbook.'.$provider.'.enabled') && filled(config('callbook.'.$provider.'.username')) && filled(config('callbook.'.$provider.'.password'));
    }

    public function key(string $provider): string
    {
        return 'callbook:'.$provider.':'.hash('sha256', config('callbook.'.$provider.'.username').'|'.config('callbook.'.$provider.'.password'));
    }

    public function token(string $provider): ?string
    {
        $value = Cache::get($this->key($provider).':token');
        if (! is_string($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            $this->forgetToken($provider);

            return null;
        }
    }

    public function rememberToken(string $provider, string $token, int $seconds): void
    {
        Cache::put($this->key($provider).':token', Crypt::encryptString($token), max(1, min($seconds, 3540)));
    }

    public function forgetToken(string $provider): void
    {
        Cache::forget($this->key($provider).':token');
    }

    public function cooldown(string $provider, int $seconds = 30): void
    {
        Cache::put($this->key($provider).':cooldown', true, max(1, min($seconds, 3600)));
    }

    public function request(string $provider, string $method, string $url, array $data = [], ?string $token = null): ?Response
    {
        if (Cache::has($this->key($provider).':cooldown')) {
            return null;
        }
        $limiter = $this->key($provider).':requests';
        if (RateLimiter::tooManyAttempts($limiter, config('callbook.requests_per_minute'))) {
            return null;
        }
        RateLimiter::hit($limiter, 60);
        try {
            // URLs constantes de cada adaptador; nunca se sigue una redirección con credenciales.
            $http = Http::connectTimeout(config('callbook.connect_timeout'))->timeout(config('callbook.timeout'))->withoutRedirecting()->withOptions(['http_errors' => false]);
            if ($token) {
                $http = $http->withToken($token);
            }
            $response = $method === 'POST' ? $http->asForm()->post($url, $data) : $http->get($url, $data);
            if ($response->status() === 429) {
                $this->cooldown($provider, (int) ($response->header('Retry-After') ?: 60));
            } elseif ($response->serverError()) {
                $this->cooldown($provider);
            }

            return $response;
        } catch (ConnectionException) {
            // No propagar excepciones del cliente: pueden contener tokens o detalles del login.
            $this->cooldown($provider);

            return null;
        }
    }

    public function matchesRequested(string $requested, string $returned): bool
    {
        $returned = strtoupper(trim($returned));

        return $returned !== '' && ($requested === $returned || in_array($returned, explode('/', $requested), true));
    }

    public function xml(?Response $response): ?SimpleXMLElement
    {
        if (! $response || ! $response->successful() || strlen($response->body()) > 262144) {
            return null;
        }
        $body = $response->body();
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $body)) {
            return null;
        }
        $old = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET);

            return $xml === false ? null : $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }
}
