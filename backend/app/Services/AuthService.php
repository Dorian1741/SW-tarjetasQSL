<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;

class AuthService
{
    public function authenticate(string $email, string $password): ?User
    {
        // Mantiene un tiempo mínimo también para correos inexistentes y cuentas suspendidas.
        return (new Timebox)->call(function () use ($email, $password) {
            $user = User::where('email', strtolower(trim($email)))->first();
            if (! $user || ! Hash::check($password, $user->password) || $user->status !== 'active') {
                return null;
            }
            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => $password])->save();
            }

            return $user;
        }, 200000);
    }
}
