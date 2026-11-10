<?php

namespace App\Services;

use App\Models\Callsign;

class CallsignLookupService
{
    public function __construct(private CallsignService $callsigns, private iterable $providers) {}

    public function lookup(string $callsign, string $provider = 'auto'): array
    {
        $normalized = $this->callsigns->validate($callsign);
        $results = [];
        $found = null;
        foreach ($this->providers as $adapter) {
            if ($provider !== 'auto' && $provider !== $adapter->name()) {
                continue;
            }
            $result = $adapter->lookup($normalized);
            $results[] = $result->toArray();
            if ($result->status === 'found') {
                $found = $result->toArray();
                break;
            }
        }
        $statuses = array_values(array_filter(array_column($results, 'status'), fn ($status) => $status !== 'unconfigured'));
        $status = $found ? 'found' : (count($statuses) > 0 && count(array_filter($statuses, fn ($s) => $s === 'not_found')) === count($statuses) ? 'not_found' : 'unavailable');

        return ['callsign' => $normalized, 'format_valid' => true, 'directory_status' => $status, 'ownership_verified' => Callsign::where('reserved_callsign', $normalized)->where('verification_status', 'verified')->whereHas('user', fn ($q) => $q->where('status', 'active'))->exists(), 'result' => $found, 'providers' => $results, 'notice' => 'Una entrada en un directorio no verifica la titularidad. Los datos externos no se guardan ni se publican en perfiles.'];
    }
}
