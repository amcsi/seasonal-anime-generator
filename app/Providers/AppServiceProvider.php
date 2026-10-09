<?php

namespace App\Providers;

use App\Mal\MalClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Date::use(CarbonImmutable::class);

        app()->singleton(MalClient::class, function () {
            $clientId = config('services.myanimelist.client_id');
            if (! $clientId) {
                throw new \RuntimeException('MYANIMELIST_CLIENT_ID is not set.');
            }

            return new MalClient($clientId);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
