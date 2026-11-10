<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\TwoFactorSessionController;
use App\Services\AuthService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TwoFactorAuthenticatedSessionController::class, TwoFactorSessionController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($user, $token) => config('fortify.home').'?reset_token='.urlencode($token).'&email='.urlencode($user->email));
        Fortify::authenticateUsing(fn (Request $request) => app(AuthService::class)->authenticate($request->string('email')->toString(), $request->string('password')->toString()));
        foreach ([ValidTwoFactorAuthenticationCodeProvided::class, TwoFactorAuthenticationConfirmed::class] as $event) {
            Event::listen($event, function ($event) {
                request()->session()->put('two_factor_proof', hash('sha256', $event->user->two_factor_secret));
            });
        }
        foreach ([Login::class, Logout::class, Failed::class] as $event) {
            Event::listen($event, function ($event) {
                if ($event instanceof Login) {
                    request()->session()->put('password_fingerprint', hash('sha256', $event->user->password));
                }
                DB::table('security_events')->insert([
                    'user_id' => $event->user?->id,
                    'event' => class_basename($event),
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);
            });
        }
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
