<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Infrastructure\Repositories\VtigerClientRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ClientRepositoryInterface::class,
            VtigerClientRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
