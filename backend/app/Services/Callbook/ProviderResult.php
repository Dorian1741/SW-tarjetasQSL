<?php

namespace App\Services\Callbook;

final readonly class ProviderResult
{
    public function __construct(public string $provider, public string $status, public ?array $record = null) {}

    public function toArray(): array
    {
        return ['provider' => $this->provider, 'status' => $this->status, 'record' => $this->record];
    }
}
