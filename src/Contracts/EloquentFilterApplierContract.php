<?php

declare(strict_types=1);

namespace Nvl\Filterable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nvl\Filterable\Data\FilterSet;
use Nvl\Filterable\Definitions\FilterSchema;

/**
 * Defines the consumer-facing EloquentFilterApplier workflow.
 *
 * @api
 */
interface EloquentFilterApplierContract
{
    /**
     * Apply filters and deterministic sorting to an Eloquent query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, FilterSet $set, FilterSchema $schema): Builder;
}
