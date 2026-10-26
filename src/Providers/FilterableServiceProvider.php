<?php

declare(strict_types=1);

namespace Nvl\Filterable\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Nvl\Filterable\Contracts\EloquentFilterApplierContract;
use Nvl\Filterable\Services\EloquentFilterApplier;
use Nvl\Filterable\Services\FilterCriterionNormalizer;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Traits\RegistersNamespacedResources;

/**
 * Registers native filtering services and publishable resources.
 */
final class FilterableServiceProvider extends ServiceProvider
{
    use RegistersNamespacedResources;

    /**
     * Register stateless filter services.
     */
    public function register(): void
    {
        $this->app->singleton(FilterCriterionNormalizer::class);
        $this->app->singletonIf(EloquentFilterApplier::class);
        $this->app->singletonIf(EloquentFilterApplierContract::class, static fn (Container $app): EloquentFilterApplierContract => $app->make(EloquentFilterApplier::class));
    }

    /**
     * Register canonical package translations and publishing.
     */
    public function boot(): void
    {
        $this->app->make(GlobalNames::class)->translations('filterable', __DIR__.'/../../lang', $this->app->make('translation.loader'));
        $this->publishes([
            __DIR__.'/../../lang' => lang_path('vendor/nvl-filterable'),
        ], 'nvl-filterable-translations');
        $this->publishes([
            __DIR__.'/../../resources/boost/skills' => base_path('.agents/skills'),
        ], 'nvl-filterable-skills');
    }
}
