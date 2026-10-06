<?php

declare(strict_types=1);

namespace Nvl\Filterable\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Nvl\Data\Services\TypeScriptSourceRegistry;
use Nvl\Filterable\Contracts\EloquentFilterApplierContract;
use Nvl\Filterable\Http\QueryFilterSetFactory;
use Nvl\Filterable\Services\EloquentFilterApplier;
use Nvl\Filterable\Services\FilterCriterionNormalizer;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Traits\RegistersNamespacedResources;

/**
 * Registers generated TypeScript discovery and publishable agent guidance.
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
        $this->app->singleton(QueryFilterSetFactory::class);
    }

    /**
     * Register package TypeScript sources and publishing.
     */
    public function boot(TypeScriptSourceRegistry $typeScriptSources): void
    {
        $this->app->make(GlobalNames::class)->translations('filterable', __DIR__.'/../../lang', $this->app->make('translation.loader'));
        $this->publishes([
            __DIR__.'/../../lang' => lang_path('vendor/nvl-filterable'),
        ], 'nvl-filterable-translations');
        $typeScriptSources->register(__DIR__.'/..', 'nvl/filterable');
        $this->publishes([
            __DIR__.'/../../resources/boost/skills' => base_path('.agents/skills'),
        ], 'filterable-skills');
    }
}
