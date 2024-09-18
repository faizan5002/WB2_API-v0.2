<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
    public function boot()
    {
        // Log connection details for debugging
        \DB::listen(function ($query) {
            \Log::info("SQL Query: {$query->sql} - Connection: " . \DB::getDefaultConnection());
        });
    }
}
