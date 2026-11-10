<?php

namespace App\Services\Callbook;

use App\Contracts\CallbookProvider;

class HamQthCallbookProvider implements CallbookProvider
{
    private const URL = 'https://www.hamqth.com/api/v2/';

    public function __construct(private CallbookTransport $transport) {}

    public function name(): string
    {
        return 'hamqth';
    }

    public function lookup(string $normalizedCallsign): ProviderResult
    {
        if (! $this->transport->configured($this->name())) {
            return new ProviderResult($this->name(), 'unconfigured');
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $token = $this->transport->token($this->name());
            if (! $token) {
                $login = $this->transport->request($this->name(), 'POST', self::URL.'login', ['callsign' => config('callbook.hamqth.username'), 'password' => config('callbook.hamqth.password')]);
                $data = $login?->successful() ? $login->json() : null;
                if (! is_array($data) || ! is_string($data['token'] ?? null) || $data['token'] === '') {
                    $this->transport->cooldown($this->name());

                    return new ProviderResult($this->name(), 'unavailable');
                }
                $token = $data['token'];
                $this->transport->rememberToken($this->name(), $token, max(1, (int) ($data['expiresIn'] ?? 60) - 30));
            }
            $response = $this->transport->request($this->name(), 'GET', self::URL.'callbook/'.rawurlencode($normalizedCallsign), ['app' => 'QSLNexus'], $token);
            if ($response?->status() === 401 && $attempt === 0) {
                $this->transport->forgetToken($this->name());

                continue;
            }
            if ($response?->status() === 404) {
                return new ProviderResult($this->name(), 'not_found');
            }
            if (! $response || ! $response->successful()) {
                return new ProviderResult($this->name(), 'unavailable');
            }
            if (strlen($response->body()) > 262144) {
                return new ProviderResult($this->name(), 'incomplete');
            }
            $record = $response->json();
            if (! is_array($record) || ! is_string($record['callsign'] ?? null) || trim($record['callsign']) === '' || ! $this->transport->matchesRequested($normalizedCallsign, $record['callsign'])) {
                return new ProviderResult($this->name(), 'incomplete');
            }

            return new ProviderResult($this->name(), 'found', ['callsign' => strtoupper(trim($record['callsign'])), 'country' => is_string($record['country'] ?? null) ? mb_substr($record['country'], 0, 100) : null]);
        }

        return new ProviderResult($this->name(), 'unavailable');
    }
}
