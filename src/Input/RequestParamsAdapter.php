<?php

declare(strict_types=1);

namespace Nvl\Filterable\Input;

use Illuminate\Support\Str;
use Nvl\Filterable\Definitions\FilterSchema;
use Nvl\Filterable\Enums\FilterOperator;
use Nvl\Filterable\Exceptions\FilterableException;
use Nvl\Filterable\Services\FilterCriterionNormalizer;

/**
 * Converts flat parameters into native Filterable input using only declared aliases.
 *
 * @api
 */
final readonly class RequestParamsAdapter
{
    public function __construct(private FilterCriterionNormalizer $normalizer) {}

    /**
     * Parse value/operator companions, CSV criteria and mapped or colon-delimited sorts.
     *
     * @param  array<array-key, mixed>  $params
     */
    public function parse(array $params, FilterSchema $schema, bool $ignoreUnknown = false): FilterInput
    {
        $operators = $values = [];
        foreach ($params as $key => $value) {
            if ($key === 'sort') {
                if ($schema->filter($key) !== null) {
                    throw new FilterableException('The flat sort key is reserved.', 'invalid_filter_shape', 'sort');
                }

                continue;
            }
            $companion = is_string($key) && preg_match('/^(.+)[._]operator$/D', $key, $parts) === 1;
            $alias = $companion ? $parts[1] : $key;
            $definition = is_string($alias) ? ($schema->filter($alias) ?? $schema->filter(Str::snake($alias))) : null;
            $direct = $companion ? ($schema->filter($key) ?? $schema->filter(Str::snake($key))) : null;
            if ($companion && $definition === null && $direct !== null) {
                $definition = $direct;
                $companion = false;
            }
            if ($definition === null) {
                if (! $ignoreUnknown) {
                    throw new FilterableException('Unknown flat filter parameter.', 'unknown_filter_alias', 'filter');
                }

                continue;
            }
            $name = $definition->alias;
            if ($companion) {
                if ($direct !== null || array_key_exists($name, $operators)) {
                    throw new FilterableException("Ambiguous operator companion for [{$name}].", 'invalid_filter_shape', "filter.{$name}.operator");
                }
                $operators[$name] = $value;
            } else {
                if (array_key_exists($name, $values)) {
                    throw new FilterableException("Duplicate flat filter alias [{$name}].", 'invalid_filter_shape', "filter.{$name}");
                }
                $values[$name] = $value;
            }
        }

        $filters = [];
        foreach ($values + $operators as $alias => $_) {
            $operator = array_key_exists($alias, $operators) ? $operators[$alias] : FilterOperator::Equals;
            if ($operator === 'not') {
                $operator = FilterOperator::NotContains;
            }
            $operator = $operator instanceof FilterOperator ? $operator : (is_string($operator) ? FilterOperator::tryFrom($operator) : null);
            if ($operator === null) {
                throw new FilterableException("Unsupported filter operator for [{$alias}].", 'unsupported_filter_operator', "filter.{$alias}.operator");
            }
            $criterion = ['alias' => $alias, 'operator' => $operator];
            if (array_key_exists($alias, $values)) {
                $value = $values[$alias];
                if (is_string($value) && in_array($operator, [FilterOperator::In, FilterOperator::NotIn, FilterOperator::Between], true)) {
                    if (strlen($value) > $schema->maximumValuesPerFilter * (2 * $schema->maximumStringLength + 3)) {
                        throw new FilterableException('The flat filter value exceeds its complexity limit.', 'filter_value_complexity_exceeded', "filter.{$alias}.value");
                    }
                    $value = str_getcsv($value, escape: '');
                }
                $criterion['value'] = $value;
            }
            $filters[] = $criterion;
        }

        $sorts = [];
        foreach ($this->sortEntries($params['sort'] ?? null, $schema) as [$alias, $direction]) {
            $definition = is_string($alias) ? ($schema->sort($alias) ?? $schema->sort(Str::snake($alias))) : null;
            if ($definition === null) {
                if (! $ignoreUnknown) {
                    throw new FilterableException('Unknown flat sort alias.', 'unknown_sort_alias', 'sort');
                }

                continue;
            }
            if (! is_string($direction)) {
                throw new FilterableException('A flat sort direction must be asc or desc.', 'invalid_sort_direction', 'sort');
            }
            $sorts[] = ['alias' => $definition->alias, 'direction' => strtolower(trim($direction))];
        }

        $this->normalizer->filters($filters, $schema);
        $this->normalizer->sorts($sorts, $schema);

        return new FilterInput($filters, $sorts);
    }

    /** @return list<array{mixed,mixed}> */
    private function sortEntries(mixed $sort, FilterSchema $schema): array
    {
        if ($sort === null || $sort === '') {
            return [];
        }
        $entries = [];
        if (is_string($sort)) {
            if (strlen($sort) > $schema->maximumSorts * ($schema->maximumStringLength + 6)) {
                throw new FilterableException('The flat sort complexity limit was exceeded.', 'sort_complexity_exceeded', 'sort');
            }
            foreach (explode(',', $sort) as $pair) {
                $parts = explode(':', $pair, 2);
                $entries[] = [trim($parts[0]), $parts[1] ?? 'asc'];
            }

            return $entries;
        }
        if (! is_array($sort)) {
            throw new FilterableException('Flat sort expects a map or comma-delimited alias:direction pairs.', 'invalid_sort_shape', 'sort');
        }
        foreach ($sort as $alias => $direction) {
            $entries[] = [$alias, $direction];
        }

        return $entries;
    }
}
