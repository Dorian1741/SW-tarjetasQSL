<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('qsl:make-admin {email}', function () {
    $user = User::where('email', strtolower($this->argument('email')))->firstOrFail();
    $user->forceFill(['is_admin' => true])->save();
    $this->info('Permiso administrativo concedido al usuario existente. Requiere correo verificado y 2FA para operar.');
})->purpose('Conceder acceso de revisión manual a un usuario existente');
