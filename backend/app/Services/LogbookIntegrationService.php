<?php

namespace App\Services;

use App\Models\IntegrationCredential;
use App\Models\IntegrationReceipt;
use Illuminate\Support\Facades\DB;

class LogbookIntegrationService
{
    public function __construct(private QsoService $qsos, private CallsignService $callsigns) {}

    public function import(IntegrationCredential $credential, array $data): array
    {
        return DB::transaction(function () use ($credential, $data) {
            DomainLock::acquire();
            $hash = $credential->token_hash;
            $credential->refresh()->load('user', 'callsign');
            abort_unless(hash_equals($hash, $credential->token_hash) && $credential->usable(), 401);
            if (isset($data['station_callsign'])) {
                abort_unless($this->callsigns->validate($data['station_callsign']) === $credential->callsign->normalized_callsign, 422, 'El indicativo no coincide con la credencial.');
            }
            $externalId = $data['external_id'];
            unset($data['external_id'],$data['station_callsign']);
            $data['contacted_callsign'] = $this->callsigns->validate($data['contacted_callsign']);
            [$whole,$fraction] = array_pad(explode('.', $data['frequency_khz'], 2), 2, '');
            $data['frequency_khz'] = (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 3, '0');
            foreach (['ended_at', 'rst_sent', 'rst_received', 'notes'] as $field) {
                $data[$field] = $data[$field] ?? null;
            }
            ksort($data);
            $requestHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $receipt = IntegrationReceipt::where('integration_credential_id', $credential->id)->where('external_id', $externalId)->first();
            if ($receipt) {
                abort_unless(hash_equals($receipt->request_hash, $requestHash), 409, 'La referencia externa ya existe con otros datos. No cambies su contenido al reintentar.');

                return [$receipt, false];
            }
            $qso = $this->qsos->create($credential->user, $data);
            $receipt = IntegrationReceipt::create(['integration_credential_id' => $credential->id, 'external_id' => $externalId, 'request_hash' => $requestHash, 'qso_id' => $qso->id]);

            return [$receipt, true];
        }, 3);
    }
}
