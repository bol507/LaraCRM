<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Application\Repositories\GeneralConditionsRepositoryInterface;
use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Application\Repositories\ProjectRepositoryInterface;
use App\Application\Repositories\QuoteRepositoryInterface;
use App\Application\Repositories\UserRepositoryInterface;
use App\Infrastructure\Repositories\VtigerClientRepository;
use App\Infrastructure\Repositories\VtigerGeneralConditionsRepository;
use App\Infrastructure\Repositories\VtigerOpportunityRepository;
use App\Infrastructure\Repositories\VtigerProjectRepository;
use App\Infrastructure\Repositories\VtigerQuoteRepository as RepositoriesVtigerQuoteRepository;
use App\Infrastructure\Repositories\VtigerUserRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {   
        $this->registerRepositories();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    private function registerRepositories(): void
    {
        // Clients
        $this->app->bind(
            ClientRepositoryInterface::class,
            VtigerClientRepository::class
        );
        // Users
        $this->app->bind(
            UserRepositoryInterface::class,
            VtigerUserRepository::class
        );
        // Opportunities
        $this->app->bind(
            OpportunityRepositoryInterface::class,
            VtigerOpportunityRepository::class
        );
        // Quotes
        $this->app->bind(
            QuoteRepositoryInterface::class,
            RepositoriesVtigerQuoteRepository::class
        );
        // General conditions
        $this->app->bind(
            GeneralConditionsRepositoryInterface::class,
            VtigerGeneralConditionsRepository::class
        );
        // Projects
        $this->app->bind(
            ProjectRepositoryInterface::class,
            VtigerProjectRepository::class
        );
    }
}
