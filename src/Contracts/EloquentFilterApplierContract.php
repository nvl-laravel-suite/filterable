<?php

declare(strict_types=1);

namespace Nvl\Filterable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nvl\Filterable\Definitions\FilterSchema;
use Nvl\Filterable\Enums\FilterOperator;
use Nvl\Filterable\Enums\SortDirection;

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
     * @param  array<array-key, mixed>  $filters  List of array{alias:string, operator:FilterOperator|string, value?:scalar|array|null}.
     * @param  array<array-key, mixed>  $sorts  List of array{alias:string, direction:SortDirection|'asc'|'desc'}.
     * @return Builder<TModel>
     */
    public function apply(Builder $query, FilterSchema $schema, array $filters = [], array $sorts = []): Builder;
}
