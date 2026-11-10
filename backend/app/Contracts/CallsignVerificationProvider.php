<?php

namespace App\Contracts;

interface CallsignVerificationProvider
{
    /** Evidencia de una fuente autorizada; nunca implica aprobación automática. @return array{source:string, reference:string, checked_at:string}|null */
    public function lookup(string $normalizedCallsign): ?array;
}
