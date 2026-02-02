<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Application\Repositories\UserRepositoryInterface;
use App\Infrastructure\Repositories\VtigerClientRepository;
use App\Infrastructure\Repositories\VtigerUserRepository;

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
        $this->app->bind(
            UserRepositoryInterface::class,
            VtigerUserRepository::class
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
