<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class AppServiceProvider extends ServiceProvider
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
        //
        $loader = AliasLoader::getInstance();
        // using custom PersonalAccessToken model instead of Sanctum's
        $loader->alias(\Laravel\Sanctum\PersonalAccessToken::class, \App\Models\PersonalAccessToken::class);
    }
}
