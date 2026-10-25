# Upgrading NVL Filterable

## Native Light API

The package targets PHP 8.4 and Laravel 13. Local family `^6.0` metadata remains provisional until the coordinated release cutover.

Replace `apply($query, $filterSet, $schema)` with `apply($query, $schema, $filters, $sorts)`. Replace `applyFilterSet($set)` with `applyFilters($filters, $sorts)`. `FilterSet`, `FilterCriterion`, `SortCriterion` and `QueryFilterSetFactory` are retired without compatibility aliases. Remove package Data and TypeScript boot/generation assumptions.

Map each filter to `['alias' => 'price', 'operator' => 'gte', 'value' => 10]`; operators may also be `FilterOperator` cases. Map each sort to `['alias' => 'price', 'direction' => 'desc']`. Both outer containers must be lists. Unknown entry keys fail closed. Null checks accept omitted value or explicit null; other operators require a value matching their declared type.

Move request query parsing and 422 response mapping into the consumer. Split comma-separated sets before calling Filterable; set/range values must be native scalar lists. Convert date objects, backed enum values and stringable values to the documented native representation. Preserve exact calendar/ISO-8601 validation. Native strict boolean, number, enum, date, string and complexity checks remain package-owned.

Custom handlers now receive a normalized array with `alias`, `operator` (`FilterOperator`) and `value` keys. Replace criterion property access with array access. Keep predicates bounded and parameterized; joins, selected columns and other query setup belong on the caller. Handlers execute inside a nested `where` group to preserve ordinary caller/related ownership predicates. They remain trusted callbacks, so authorize the query before applying filters.

`EloquentFilterApplierContract` remains the substitution seam and the model scope resolves it. Real definitions, operator/value enums, schema validation, default sorts, tie-breakers, literal contains escaping and native exception context remain available. Canonical resource publication uses `nvl-filterable-skills` and `nvl-filterable-translations`.
