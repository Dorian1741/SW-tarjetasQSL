<?php

namespace App\Contracts;

use App\Services\Callbook\ProviderResult;

interface CallbookProvider
{
    public function name(): string;

    public function lookup(string $normalizedCallsign): ProviderResult;
}
