<?php

namespace App\Providers;

use App\Extractor\JikanFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Jikan\JikanPHP\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Date::use(CarbonImmutable::class);

        app()->singleton(Client::class, fn () => JikanFactory::create());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
