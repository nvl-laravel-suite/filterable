<?php

declare(strict_types=1);

namespace Nvl\Filterable\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Nvl\Filterable\Contracts\EloquentFilterApplierContract;
use Nvl\Filterable\Definitions\FilterDefinition;
use Nvl\Filterable\Definitions\FilterSchema;
use Nvl\Filterable\Enums\FilterOperator;
use Nvl\Filterable\Enums\SortDirection;
use Nvl\Filterable\Exceptions\FilterableException;

/**
 * Applies validated aliases without accepting raw database identifiers.
 *
 * @api
 */
final readonly class EloquentFilterApplier implements EloquentFilterApplierContract
{
    /**
     * Create an Eloquent filter applier.
     */
    public function __construct(
        private FilterCriterionNormalizer $normalizer,
    ) {}

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
    public function apply(Builder $query, FilterSchema $schema, array $filters = [], array $sorts = []): Builder
    {
        $filters = $this->normalizer->filters($filters, $schema);
        $sorts = $this->normalizer->sorts($sorts, $schema);
        foreach ($filters as $criterion) {
            $definition = $schema->filter($criterion['alias'])
                ?? throw new LogicException("Normalized filter [{$criterion['alias']}] is not declared.");
            $this->applyDefinition($query, $criterion, $definition);
        }
        foreach ($sorts as $sort) {
            $definition = $schema->sort($sort['alias'])
                ?? throw new LogicException("Normalized sort [{$sort['alias']}] is not declared.");
            $query->orderBy($definition->column, $sort['direction']);
        }

        return $query;
    }

    /**
     * Apply one declared filter, including a registered relation alias.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{alias: string, operator: FilterOperator, value: mixed}  $criterion
     */
    private function applyDefinition(
        Builder $query,
        array $criterion,
        FilterDefinition $definition,
    ): void {
        if ($definition->handler !== null) {
            $query->where(function (Builder $nested) use ($definition, $criterion): void {
                ($definition->handler)($nested, $criterion);
            });

            return;
        }

        $segments = explode('.', $definition->column);
        $column = array_pop($segments);

        if ($segments === []) {
            $this->applyOperator($query, $column, $criterion);

            return;
        }

        $query->whereHas(
            implode('.', $segments),
            fn (Builder $related): Builder => $this->applyOperator($related, $column, $criterion),
        );
    }

    /**
     * Apply one normalized operator.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{alias: string, operator: FilterOperator, value: mixed}  $criterion
     * @return Builder<TModel>
     */
    private function applyOperator(
        Builder $query,
        string $column,
        array $criterion,
    ): Builder {
        $operator = $criterion['operator'];

        if ($operator === FilterOperator::IsNull) {
            return $query->whereNull($column);
        }

        if ($operator === FilterOperator::IsNotNull) {
            return $query->whereNotNull($column);
        }

        if ($operator === FilterOperator::In) {
            return $query->whereIn($column, $this->normalizedList($criterion));
        }

        if ($operator === FilterOperator::NotIn) {
            return $query->whereNotIn($column, $this->normalizedList($criterion));
        }

        if ($operator === FilterOperator::Between) {
            return $query->whereBetween($column, $this->normalizedList($criterion));
        }

        $value = $this->queryValue($criterion['value']);

        return match ($operator) {
            FilterOperator::Equals => $query->where($column, '=', $value),
            FilterOperator::NotEquals => $query->where($column, '!=', $value),
            FilterOperator::Contains => $this->whereContains($query, $column, $criterion, false),
            FilterOperator::NotContains => $this->whereContains($query, $column, $criterion, true),
            FilterOperator::Before, FilterOperator::Lt => $query->where($column, '<', $value),
            FilterOperator::After, FilterOperator::Gt => $query->where($column, '>', $value),
            FilterOperator::Gte => $query->where($column, '>=', $value),
            FilterOperator::Lte => $query->where($column, '<=', $value),
        };
    }

    /**
     * Apply literal substring matching with portable wildcard escaping.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{alias: string, operator: FilterOperator, value: mixed}  $criterion
     * @return Builder<TModel>
     */
    private function whereContains(
        Builder $query,
        string $column,
        array $criterion,
        bool $negated,
    ): Builder {
        if (! is_string($criterion['value'])) {
            throw new FilterableException(
                "Filter [{$criterion['alias']}] requires a string value.",
                'invalid_string_filter_value',
                "filter.{$criterion['alias']}.value",
            );
        }

        $grammar = $query->getQuery()->getGrammar();
        $wrapped = $grammar->wrap($column);
        $this->ensureSafeWrappedColumn($wrapped);
        $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $criterion['value']).'%';

        return $negated
            ? $query->whereRaw("{$wrapped} NOT LIKE ? ESCAPE '!'", [$needle])
            : $query->whereRaw("{$wrapped} LIKE ? ESCAPE '!'", [$needle]);
    }

    /**
     * Read an already-normalized list value.
     *
     * @param  array{alias: string, operator: FilterOperator, value: mixed}  $criterion
     * @return list<mixed>
     */
    private function normalizedList(array $criterion): array
    {
        if (! is_array($criterion['value']) || ! array_is_list($criterion['value'])) {
            throw new FilterableException(
                "Filter [{$criterion['alias']}] requires a normalized value list.",
                'invalid_filter_value_shape',
                "filter.{$criterion['alias']}.value",
            );
        }

        return array_map($this->queryValue(...), $criterion['value']);
    }

    /** Preserve fractional instants before Laravel formats date objects to whole seconds. */
    private function queryValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface && $value->format('u') !== '000000') {
            return $value->format('Y-m-d H:i:s.u');
        }

        return $value;
    }

    /**
     * Prove that a database grammar returned only one quoted, allowlisted identifier.
     *
     * @phpstan-assert literal-string $column
     */
    private function ensureSafeWrappedColumn(string $column): void
    {
        if (preg_match('/\A(?:[A-Za-z_][A-Za-z0-9_]*|`[A-Za-z_][A-Za-z0-9_]*`|"[A-Za-z_][A-Za-z0-9_]*"|\[[A-Za-z_][A-Za-z0-9_]*\])\z/', $column) !== 1) {
            throw new FilterableException(
                'The database grammar returned an unsafe column identifier.',
                'unsafe_wrapped_filter_column',
            );
        }
    }
}
