<?php

namespace App\Providers;

use App\Application\Repositories\ActivityLogRepositoryInterface;
use App\Application\Repositories\AttachmentRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use App\Application\Repositories\ClientRepositoryInterface;
use App\Application\Repositories\CommentRepositoryInterface;
use App\Application\Repositories\ContactRepositoryInterface;
use App\Application\Repositories\DashboardRepositoryInterface;
use App\Application\Repositories\GeneralConditionsRepositoryInterface;
use App\Application\Repositories\OpportunityRepositoryInterface;
use App\Application\Repositories\ProjectRepositoryInterface;
use App\Application\Repositories\QuoteRepositoryInterface;
use App\Application\Repositories\TaskRepositoryInterface;
use App\Application\Repositories\UserRepositoryInterface;
use App\Infrastructure\Repositories\VtigerActivityLogRepository;
use App\Infrastructure\Repositories\VtigerAttachmentRepository;
use App\Infrastructure\Repositories\VtigerClientRepository;
use App\Infrastructure\Repositories\VtigerCommentRepository;
use App\Infrastructure\Repositories\VtigerContactRepository;
use App\Infrastructure\Repositories\VtigerDashboardRepository;
use App\Infrastructure\Repositories\VtigerGeneralConditionsRepository;
use App\Infrastructure\Repositories\VtigerOpportunityRepository;
use App\Infrastructure\Repositories\VtigerProjectRepository;
use App\Infrastructure\Repositories\VtigerQuoteRepository;
use App\Infrastructure\Repositories\VtigerTaskRepository;
use App\Infrastructure\Repositories\VtigerUserRepository;
use App\Services\JwtService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         $this->app->singleton(JwtService::class, function ($app) {
            return new JwtService();
        });
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
            VtigerQuoteRepository::class
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
        // Comments
        $this->app->bind(
            CommentRepositoryInterface::class,
            VtigerCommentRepository::class
        );
        // Attachments
        $this->app->bind(
            AttachmentRepositoryInterface::class,
            VtigerAttachmentRepository::class
        );
        // Register Task Repository
        $this->app->bind(
            TaskRepositoryInterface::class,
            VtigerTaskRepository::class
        );
        // Dashboard
        $this->app->bind(
            DashboardRepositoryInterface::class,
            VtigerDashboardRepository::class
        );
        // Contacts
        $this->app->bind(
            ContactRepositoryInterface::class,
            VtigerContactRepository::class
        );
        // Activities
        $this->app->bind(
            ActivityLogRepositoryInterface::class,
            VtigerActivityLogRepository::class
        );

    }
}
