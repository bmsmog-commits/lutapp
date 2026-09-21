<?php

namespace App\Providers;

use App\Models\Organization;
use App\Observers\OrganizationObserver;
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
