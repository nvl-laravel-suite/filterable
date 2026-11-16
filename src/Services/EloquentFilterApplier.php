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
use Nvl\Support\Contracts\TextMatcher;

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
        private ?TextMatcher $textMatcher = null,
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
            $query->orderBy($this->qualifyForQuery($query, $definition->column), $sort['direction']);
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
        if ($definition->transform !== null) {
            $transformed = ($definition->transform)(clone $query, $criterion);
            $this->applyTransform($query, $transformed, $criterion['alias']);

            return;
        }
        $handler = $definition->handler;
        if ($handler !== null) {
            $query->where(function (Builder $nested) use ($handler, $criterion): void {
                $result = $handler($nested, $criterion);
                if ($result instanceof Builder && $result !== $nested) {
                    if (! $this->usesSameSource($nested, $result)) {
                        throw new FilterableException('A filter handler must preserve its query source.', 'invalid_filter_handler', "filter.{$criterion['alias']}");
                    }
                    $nested->setQuery($result->getQuery());
                }
            });

            return;
        }

        $segments = explode('.', $definition->column);
        $column = array_pop($segments);

        if ($segments === []) {
            $this->applyOperator($query, $this->qualifyForQuery($query, $column), $criterion, $definition);

            return;
        }

        $query->whereHas(
            implode('.', $segments),
            fn (Builder $related): Builder => $this->applyOperator($related, $related->qualifyColumn($column), $criterion, $definition),
        );
    }

    /**
     * A transform may add query clauses and ordering while retaining the caller's source and constraints.
     *
     * @param  Builder<*>  $query
     * @param  Builder<*>  $result
     */
    private function applyTransform(Builder $query, Builder $result, string $alias): void
    {
        $original = $query->getQuery();
        $transformed = $result->getQuery();
        $whereCount = count($original->wheres);
        $originalBindings = $original->getRawBindings()['where'];
        $candidateBindings = $transformed->getRawBindings()['where'];
        $valid = $this->usesSameSource($query, $result)
            && array_slice($transformed->wheres, 0, $whereCount) === $original->wheres
            && array_slice($candidateBindings, 0, count($originalBindings)) === $originalBindings
            && $transformed->unions === $original->unions
            && $transformed->joins === $original->joins
            && $transformed->havings === $original->havings
            && $transformed->getRawBindings()['join'] === $original->getRawBindings()['join']
            && $transformed->getRawBindings()['having'] === $original->getRawBindings()['having']
            && $query->removedScopes() === $result->removedScopes();
        foreach (array_slice($transformed->wheres, $whereCount) as $clause) {
            if (! is_array($clause) || ($clause['boolean'] ?? null) !== 'and') {
                $valid = false;
            }
        }
        if (! $valid) {
            throw new FilterableException('A filter transform must preserve the existing query constraints and group any OR predicates.', 'invalid_filter_transform', "filter.{$alias}");
        }
        $query->setQuery($transformed);
        $query->setEagerLoads($result->getEagerLoads());
    }

    /**
     * @param  Builder<*>  $query
     * @param  Builder<*>  $result
     */
    private function usesSameSource(Builder $query, Builder $result): bool
    {
        return $query->getModel()::class === $result->getModel()::class
            && $query->getQuery()->from === $result->getQuery()->from
            && $query->getQuery()->getConnection() === $result->getQuery()->getConnection();
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
        FilterDefinition $definition,
    ): Builder {
        $operator = $criterion['operator'];
        $dateFormat = $query->getModel()->getDateFormat();

        if ($operator === FilterOperator::IsNull) {
            return $query->whereNull($column);
        }

        if ($operator === FilterOperator::IsNotNull) {
            return $query->whereNotNull($column);
        }

        if ($definition->calendarDate) {
            $this->whereCalendarDate($query, $column, $criterion);

            return $query;
        }

        if ($operator === FilterOperator::In) {
            return $query->whereIn($column, $this->normalizedList($criterion, $dateFormat));
        }

        if ($operator === FilterOperator::NotIn) {
            return $query->whereNotIn($column, $this->normalizedList($criterion, $dateFormat));
        }

        if ($operator === FilterOperator::Between) {
            return $query->whereBetween($column, $this->normalizedList($criterion, $dateFormat));
        }

        $value = $this->queryValue($criterion['value'], $dateFormat);

        return match ($operator) {
            FilterOperator::Equals => $query->where($column, '=', $value),
            FilterOperator::NotEquals => $query->where($column, '!=', $value),
            FilterOperator::Contains => $this->whereContains($query, $column, $criterion, false, $definition->allowWildcards),
            FilterOperator::NotContains => $this->whereContains($query, $column, $criterion, true, $definition->allowWildcards),
            FilterOperator::Before, FilterOperator::Lt => $query->where($column, '<', $value),
            FilterOperator::After, FilterOperator::Gt => $query->where($column, '>', $value),
            FilterOperator::Gte => $query->where($column, '>=', $value),
            FilterOperator::Lte => $query->where($column, '<=', $value),
        };
    }

    /**
     * @param  Builder<*>  $query
     * @param  array{alias: string, operator: FilterOperator, value: mixed}  $criterion
     */
    private function whereCalendarDate(Builder $query, string $column, array $criterion): void
    {
        $operator = $criterion['operator'];
        if (in_array($operator, [FilterOperator::In, FilterOperator::NotIn, FilterOperator::Between], true)) {
            $values = $this->normalizedList($criterion);
            $query->where(static function (Builder $nested) use ($values, $operator, $column): void {
                foreach ($values as $index => $value) {
                    if (! is_string($value)) {
                        throw new LogicException('Expected a normalized calendar date.');
                    }
                    $comparison = match ($operator) {
                        FilterOperator::Between => $index === 0 ? '>=' : '<=',
                        FilterOperator::NotIn => '!=',
                        default => '=',
                    };
                    $nested->getQuery()->whereDate($column, $comparison, $value, $operator === FilterOperator::In ? 'or' : 'and');
                }
            });

            return;
        }
        $comparison = match ($operator) {
            FilterOperator::Equals => '=',
            FilterOperator::NotEquals => '!=',
            FilterOperator::Before, FilterOperator::Lt => '<',
            FilterOperator::After, FilterOperator::Gt => '>',
            FilterOperator::Lte => '<=',
            FilterOperator::Gte => '>=',
            default => throw new LogicException('Unexpected calendar date operator.'),
        };
        $value = $criterion['value'];
        if (! is_string($value)) {
            throw new LogicException('Expected a normalized calendar date.');
        }
        $query->getQuery()->whereDate($column, $comparison, $value);
    }

    /**
     * Qualify bare columns when the query has joins so joined queries never fail on ambiguous identifiers.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function qualifyForQuery(Builder $query, string $column): string
    {
        if (str_contains($column, '.') || ($query->getQuery()->joins ?? []) === []) {
            return $column;
        }

        return $query->qualifyColumn($column);
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
        bool $wildcards,
    ): Builder {
        if (! is_string($criterion['value'])) {
            throw new FilterableException(
                "Filter [{$criterion['alias']}] requires a string value.",
                'invalid_string_filter_value',
                "filter.{$criterion['alias']}.value",
            );
        }

        $this->ensureSafeWrappedColumn($query->getQuery()->getGrammar()->wrap($column));
        $matcher = $this->textMatcher ?? app(TextMatcher::class);
        $query->setQuery($matcher->contains($query->getQuery(), $column, $criterion['value'], $negated, $wildcards));

        return $query;
    }

    /**
     * Read an already-normalized list value.
     *
     * @param  array{alias: string, operator: FilterOperator, value: mixed}  $criterion
     * @return list<mixed>
     */
    private function normalizedList(array $criterion, ?string $dateFormat = null): array
    {
        if (! is_array($criterion['value']) || ! array_is_list($criterion['value'])) {
            throw new FilterableException(
                "Filter [{$criterion['alias']}] requires a normalized value list.",
                'invalid_filter_value_shape',
                "filter.{$criterion['alias']}.value",
            );
        }

        return array_map(
            fn (mixed $item): mixed => $this->queryValue($item, $dateFormat),
            $criterion['value'],
        );
    }

    /** Preserve fractional instants and match microsecond model date formats on SQLite. */
    private function queryValue(mixed $value, ?string $dateFormat = null): mixed
    {
        if ($value instanceof DateTimeInterface) {
            if ($value->format('u') !== '000000') {
                return $value->format('Y-m-d H:i:s.u');
            }

            if ($dateFormat !== null && str_contains($dateFormat, 'u')) {
                return $value->format($dateFormat);
            }
        }

        return $value;
    }

    /**
     * Prove that a database grammar returned only a safely qualified identifier.
     */
    private function ensureSafeWrappedColumn(string $column): void
    {
        if (preg_match('/\A(?:(?:[A-Za-z_][A-Za-z0-9_]*|`[A-Za-z_][A-Za-z0-9_]*`|"[A-Za-z_][A-Za-z0-9_]*"|\[[A-Za-z_][A-Za-z0-9_]*\])\.){0,2}(?:[A-Za-z_][A-Za-z0-9_]*|`[A-Za-z_][A-Za-z0-9_]*`|"[A-Za-z_][A-Za-z0-9_]*"|\[[A-Za-z_][A-Za-z0-9_]*\])\z/', $column) !== 1) {
            throw new FilterableException(
                'The database grammar returned an unsafe column identifier.',
                'unsafe_wrapped_filter_column',
            );
        }
    }
}
