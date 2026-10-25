<?php

declare(strict_types=1);

namespace Nvl\Filterable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nvl\Filterable\Contracts\EloquentFilterApplierContract;
use Nvl\Filterable\Definitions\FilterSchema;

/**
 * Applies native filters and sorts using the model's immutable allowlist.
 *
 * @template TModel of Model
 *
 * @api
 */
trait Filterable
{
    /**
     * Declare public filter and sort aliases for this model.
     */
    abstract public function filterSchema(): FilterSchema;

    /**
     * Apply native filters and sorts.
     *
     * @param  Builder<TModel>  $query
     * @param  array<array-key, mixed>  $filters  Native filter list; see EloquentFilterApplierContract.
     * @param  array<array-key, mixed>  $sorts  Native sort list; see EloquentFilterApplierContract.
     * @return Builder<TModel>
     */
    public function scopeApplyFilters(Builder $query, array $filters = [], array $sorts = []): Builder
    {
        return app(EloquentFilterApplierContract::class)
            ->apply($query, $this->filterSchema(), $filters, $sorts);
    }
}
