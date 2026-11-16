# Upgrading NVL Filterable

## Unreleased: PostgreSQL date-time bindings

Date-time filter values are now bound as `Y-m-d H:i:s.uP` UTC instants on PostgreSQL. `timestamptz` columns therefore match the requested instant even when the session time zone is not UTC. `timestamp` columns ignore the offset and keep comparing UTC wall times. A consumer that asserted exact offset-free PostgreSQL bindings, or filters a text column with the default date format, should expect the `+00:00` suffix; declare a model date format containing `u` to keep a custom text representation. Such a format is bound as declared, so on a `timestamptz` column include an offset (`Y-m-d H:i:s.uP`). SQLite bindings are unchanged.

## Native Light API

The package targets PHP 8.4, Laravel 13 and the coordinated NVL 7.x family. Upgrade the selected dependency closure together.

Replace `apply($query, $filterSet, $schema)` with `apply($query, $schema, $filters, $sorts)`. Replace `applyFilterSet($set)` with `applyFilters($filters, $sorts)`. `FilterSet`, `FilterCriterion`, `SortCriterion` and `QueryFilterSetFactory` are retired without compatibility aliases. Remove package Data and TypeScript boot/generation assumptions.

Map each filter to `['alias' => 'price', 'operator' => 'gte', 'value' => 10]`; operators may also be `FilterOperator` cases. Map each sort to `['alias' => 'price', 'direction' => 'desc']`. Both outer containers must be lists. Unknown entry keys fail closed. Null checks accept omitted value or explicit null; other operators require a value matching their declared type.

Move request query parsing and 422 response mapping into the consumer. Split comma-separated sets before calling Filterable; set/range values must be native scalar lists. Convert date objects, backed enum values and stringable values to the documented native representation. Preserve exact calendar/ISO-8601 validation. Native strict boolean, number, enum, date, string and complexity checks remain package-owned.

Custom handlers now receive a normalized array with `alias`, `operator` (`FilterOperator`) and `value` keys. Replace criterion property access with array access. Keep predicates bounded and parameterized; joins, selected columns and other query setup belong on the caller. Handlers execute inside a nested `where` group to preserve ordinary caller/related ownership predicates. They remain trusted callbacks, so authorize the query before applying filters.

`EloquentFilterApplierContract` remains the substitution seam and the model scope resolves it. Real definitions, operator/value enums, schema validation, default sorts, tie-breakers, literal contains escaping and native exception context remain available. Canonical resource publication uses `nvl-filterable-skills` and `nvl-filterable-translations`.
