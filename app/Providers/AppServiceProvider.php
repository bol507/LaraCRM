<?php

namespace App\Providers;

use App\Application\Contracts\ActivityTrackerInterface;
use App\Application\Contracts\GoogleDriveServiceInterface;
use App\Application\Repositories\ActivityLogRepositoryInterface;
use App\Application\Repositories\AttachmentRepositoryInterface;
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
use App\Application\UseCases\Core\Activity\CreateTaskActivityUseCase;
use App\Application\UseCases\Core\Activity\UpdateTaskActivityUseCase;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Infrastructure\Repositories\AccountRepository;
use App\Infrastructure\Repositories\BillingAddressRepository;
use App\Infrastructure\Repositories\CommentRepository;
use App\Infrastructure\Repositories\ContactRepository;
use App\Infrastructure\Repositories\Core\ActivityRepository;
use App\Infrastructure\Repositories\Core\CrmentityRepository;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use App\Infrastructure\Repositories\Core\SeActivityRelRepository;
use App\Infrastructure\Repositories\PotentialRepository;
use App\Infrastructure\Repositories\ProjectRepository;
use App\Infrastructure\Repositories\QuoteRepository;
use App\Infrastructure\Repositories\ShippingAddressRepository;
use App\Infrastructure\Repositories\VtigerActivityLogRepository;
use App\Infrastructure\Repositories\VtigerAttachmentRepository;
use App\Infrastructure\Repositories\VtigerDashboardRepository;
use App\Infrastructure\Repositories\VtigerGeneralConditionsRepository;
use App\Infrastructure\Repositories\VtigerTaskRepository;
use App\Infrastructure\Repositories\VtigerUserRepository;
use App\Services\ActivityTrackerWrapper;
use App\Services\GoogleDrive\GoogleDriveFactory;
use App\Services\JwtService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        

        // Core repositories (singleton - shared across all use cases)
        $this->app->singleton(CrmentityRepository::class);
        $this->app->singleton(ActivityRepository::class);
        $this->app->singleton(SeActivityRelRepository::class);
        $this->app->singleton(IdGeneratorRepository::class);

        // Generic UseCases (singleton - reused by all module-specific UseCases)
        $this->app->singleton(CreateEntityUseCase::class);
        $this->app->singleton(UpdateEntityUseCase::class);
        $this->app->singleton(CreateTaskActivityUseCase::class);
        $this->app->singleton(UpdateTaskActivityUseCase::class);

        // Module-specific repositories (singleton)
        $this->app->singleton(AccountRepository::class);
        $this->app->singleton(BillingAddressRepository::class);
        $this->app->singleton(ShippingAddressRepository::class);
        $this->app->singleton(ContactRepository::class);
        $this->app->singleton(PotentialRepository::class);
        $this->app->singleton(QuoteRepository::class);
        $this->app->singleton(ProjectRepository::class);
        $this->app->singleton(CommentRepository::class);

        $this->registerServices();
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
        // Clients - use new AccountRepository (implements ClientRepositoryInterface)
        $this->app->bind(
            ClientRepositoryInterface::class,
            AccountRepository::class
        );
        // Users
        $this->app->bind(
            UserRepositoryInterface::class,
            VtigerUserRepository::class
        );
        // Opportunities - use new PotentialRepository (implements OpportunityRepositoryInterface)
        $this->app->bind(
            OpportunityRepositoryInterface::class,
            PotentialRepository::class
        );
        // Quotes - use new QuoteRepository (implements QuoteRepositoryInterface)
        $this->app->bind(
            QuoteRepositoryInterface::class,
            QuoteRepository::class
        );
        // General conditions
        $this->app->bind(
            GeneralConditionsRepositoryInterface::class,
            VtigerGeneralConditionsRepository::class
        );
        // Projects - use new ProjectRepository (implements ProjectRepositoryInterface)
        $this->app->bind(
            ProjectRepositoryInterface::class,
            ProjectRepository::class
        );
        // Comments - use new CommentRepository (implements CommentRepositoryInterface)
        $this->app->bind(
            CommentRepositoryInterface::class,
            CommentRepository::class
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
        // Contacts - use new ContactRepository (implements ContactRepositoryInterface)
        $this->app->bind(
            ContactRepositoryInterface::class,
            ContactRepository::class
        );
        // Activities
        $this->app->bind(
            ActivityLogRepositoryInterface::class,
            VtigerActivityLogRepository::class
        );

    }

    private function registerServices(): void
    {
        // JWT
        $this->app->singleton(JwtService::class, function ($app) {
            return new JwtService;
        });
        // Activity tracker
        $this->app->singleton(
            ActivityTrackerInterface::class,
            ActivityTrackerWrapper::class
        );
        // Google Drive
        $this->app->singleton(GoogleDriveServiceInterface::class, function ($app) {
            return GoogleDriveFactory::create();
        });
    }
}
