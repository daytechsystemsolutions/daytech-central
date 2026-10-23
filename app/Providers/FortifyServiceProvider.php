<?php

namespace App\Providers;

use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Entrar com senha (e código) já vale como "confirmou a senha agora": quem acabou de entrar
        // configura o 2FA sem digitar a senha de novo. Entrada pelo "manter conectado" não conta.
        Event::listen(Login::class, function () {
            if (request()->isMethod('post') && request()->is('login', 'two-factor-challenge')) {
                request()->session()->put('auth.password_confirmed_at', time());
            }
        });

        Fortify::loginView(fn () => view('auth.entrar'));
        Fortify::twoFactorChallengeView(fn () => view('auth.dois-fatores'));
        Fortify::confirmPasswordView(fn () => view('auth.confirmar-senha'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

    }
}
