<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Passport::routes();
        Passport::personalAccessTokensExpireIn(now()->addDay(30));
        Passport::refreshTokensExpireIn(now()->addMinutes(60));
        Passport::loadKeysFrom(base_path().'./secret-key');
    }
}
