<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DomainLock
{
    public static function acquire(): void
    {
        // Una fila serializa escrituras de QSO en el MVP. Todos los servicios usan el mismo orden.
        DB::table('domain_locks')->where('name', 'qsos')->lockForUpdate()->firstOrFail();
    }
}
