<?php

namespace App\Providers;

use App\Models\Organization;
use App\Observers\OrganizationObserver;
use App\Services\Ai\AiAssistantInterface;
use App\Services\Ai\NullAiAssistant;
use App\Services\Messaging\NullSmsVerificationProvider;
use App\Services\Messaging\NullWhatsAppProvider;
use App\Services\Messaging\SmsVerificationProvider;
use App\Services\Messaging\WhatsAppProvider;
use App\Services\Payments\PaymentProviderContract;
use App\Services\Payments\PaymentProviderFactory;
use App\Services\SearchService;
use App\Support\Search\Providers\AudioSearchProvider;
use App\Support\Search\Providers\BibleSearchProvider;
use App\Support\Search\Providers\EventSearchProvider;
use App\Support\Search\Providers\JobSearchProvider;
use App\Support\Search\Providers\OrganizationSearchProvider;
use App\Support\Search\Providers\ResourceSearchProvider;
use App\Support\Search\Providers\UserSearchProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentProviderContract::class, fn () => PaymentProviderFactory::make());

        // Phase 27.6 — provider seams with null implementations only.
        // Nothing here sends an SMS or a WhatsApp message, and nothing here
        // calls an AI vendor. Swapping in a real adapter is a deliberate,
        // credentialed future change; there is no fallback that fakes success.
        $this->app->singleton(SmsVerificationProvider::class, NullSmsVerificationProvider::class);
        $this->app->singleton(WhatsAppProvider::class, NullWhatsAppProvider::class);
        $this->app->singleton(AiAssistantInterface::class, NullAiAssistant::class);

        // Registration order is display order for the "All" unified view.
        $this->app->singleton(SearchService::class, fn () => new SearchService([
            new OrganizationSearchProvider(),
            new JobSearchProvider(),
            new EventSearchProvider(),
            new ResourceSearchProvider(),
            new AudioSearchProvider(),
            new UserSearchProvider(),
            new BibleSearchProvider(),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Organization::observe(OrganizationObserver::class);
    }
}
