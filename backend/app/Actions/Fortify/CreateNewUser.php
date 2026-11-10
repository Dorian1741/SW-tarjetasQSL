<?php

namespace App\Actions\Fortify;

use App\Models\OperatorProfile;
use App\Models\User;
use App\Services\CallsignService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = strtolower(trim($input['email']));
        }

        Validator::make($input, [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'callsign' => ['required', 'string', 'max:32'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $callsigns = app(CallsignService::class);
        $normalized = $callsigns->validate($input['callsign']);
        try {
            return DB::transaction(function () use ($input, $callsigns, $normalized) {
                $user = User::create(['name' => $input['name'] ?? $normalized, 'email' => $input['email'], 'password' => Hash::make($input['password'])]);
                $claim = $callsigns->claim($user, $normalized);
                OperatorProfile::create(['user_id' => $user->id, 'callsign_id' => $claim->id, 'is_public' => false]);

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Este correo ya está registrado.']);
        }
    }
}
