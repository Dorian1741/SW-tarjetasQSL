<?php

namespace App\Services\Callbook;

use App\Contracts\CallbookProvider;

class QrzCallbookProvider implements CallbookProvider
{
    private const URL = 'https://xmldata.qrz.com/xml/current/';

    public function __construct(private CallbookTransport $transport) {}

    public function name(): string
    {
        return 'qrz';
    }

    public function lookup(string $normalizedCallsign): ProviderResult
    {
        if (! $this->transport->configured($this->name())) {
            return new ProviderResult($this->name(), 'unconfigured');
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $token = $this->transport->token($this->name());
            if (! $token) {
                $login = $this->transport->request($this->name(), 'POST', self::URL, ['username' => config('callbook.qrz.username'), 'password' => config('callbook.qrz.password'), 'agent' => 'QSLNexus/1.0']);
                $xml = $this->transport->xml($login);
                $session = $xml?->xpath('/*[local-name()="QRZDatabase"]/*[local-name()="Session"]')[0] ?? null;
                $token = $session ? trim((string) ($session->xpath('./*[local-name()="Key"]')[0] ?? '')) : '';
                if (! $token || trim((string) ($session->xpath('./*[local-name()="Error"]')[0] ?? '')) !== '') {
                    $this->transport->cooldown($this->name());

                    return new ProviderResult($this->name(), 'unavailable');
                }
                $this->transport->rememberToken($this->name(), $token, 900);
            }
            // POST también para la consulta: el token no queda en URLs o logs de acceso.
            $response = $this->transport->request($this->name(), 'POST', self::URL, ['s' => $token, 'callsign' => $normalizedCallsign]);
            $xml = $this->transport->xml($response);
            if (! $xml) {
                return new ProviderResult($this->name(), $response?->successful() ? 'incomplete' : 'unavailable');
            }
            $session = $xml->xpath('/*[local-name()="QRZDatabase"]/*[local-name()="Session"]')[0] ?? null;
            // Acceso por nombre local para las respuestas con namespace XML por defecto.
            $error = $session ? (string) ($session->xpath('./*[local-name()="Error"]')[0] ?? '') : '';
            if (preg_match('/not found|callsign not found/i', $error)) {
                return new ProviderResult($this->name(), 'not_found');
            }
            $key = $session ? (string) ($session->xpath('./*[local-name()="Key"]')[0] ?? '') : '';
            if (! $key && preg_match('/session|timeout/i', $error) && $attempt === 0) {
                $this->transport->forgetToken($this->name());

                continue;
            }
            if ($error !== '') {
                return new ProviderResult($this->name(), 'unavailable');
            }
            $record = $xml->xpath('/*[local-name()="QRZDatabase"]/*[local-name()="Callsign"]')[0] ?? null;
            $call = $record ? trim((string) ($record->xpath('./*[local-name()="call"]')[0] ?? '')) : '';
            if (! $record || ! $call || ! $key || ! $this->transport->matchesRequested($normalizedCallsign, $call)) {
                return new ProviderResult($this->name(), 'incomplete');
            }
            $country = trim((string) ($record->xpath('./*[local-name()="country"]')[0] ?? ''));

            // Lista mínima permitida: no devolver nombre, correo, dirección, foto ni biografía ajena.
            return new ProviderResult($this->name(), 'found', ['callsign' => strtoupper($call), 'country' => $country !== '' ? mb_substr($country, 0, 100) : null]);
        }

        return new ProviderResult($this->name(), 'unavailable');
    }
}
