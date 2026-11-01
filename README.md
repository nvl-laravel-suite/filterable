# NVL Filterable — API and usage

## Quickstart

```sh
composer require nvl/filterable:^7.0
php artisan vendor:publish --tag=nvl-filterable-translations
php artisan vendor:publish --tag=nvl-filterable-skills
```

Requires PHP 8.4+, Laravel 13 and `nvl/core:^7.0` for native exception presentation and resource names. There are no routes, migrations, config files, Data dependencies or frontend transformation runtime.

Authorize a host-owned model query, declare a `FilterSchema`, then inject `Nvl\Filterable\Contracts\EloquentFilterApplierContract`:

```php
$query = $applier->apply(
    Article::query()->where('account_id', $accountId),
    $schema,
    filters: [['alias' => 'status', 'operator' => 'in', 'value' => ['draft', 'published']]],
    sorts: [['alias' => 'publishedAt', 'direction' => 'desc']],
);
$articles = $query->paginate();
```

## Declare a schema

Every public alias maps to a known column, relation, value type, operator set, null rule, or custom handler:

```php
use Nvl\Filterable\Definitions\FilterDefinition;
use Nvl\Filterable\Definitions\FilterSchema;
use Nvl\Filterable\Definitions\SortDefinition;
use Nvl\Filterable\Enums\FilterOperator;
use Nvl\Filterable\Enums\FilterValueType;

$schema = new FilterSchema(
    filters: [
        new FilterDefinition(
            alias: 'status',
            column: 'status',
            type: FilterValueType::Enum,
            operators: [FilterOperator::Equals, FilterOperator::In],
            enumValues: ['draft', 'published'],
        ),
        new FilterDefinition(
            alias: 'author',
            column: 'author.name',
            type: FilterValueType::String,
            operators: [FilterOperator::Contains],
        ),
        new FilterDefinition(
            alias: 'publishedAt',
            column: 'published_at',
            type: FilterValueType::DateTime,
            operators: [
                FilterOperator::Before,
                FilterOperator::After,
                FilterOperator::Between,
                FilterOperator::IsNull,
            ],
            nullable: true,
        ),
    ],
    sorts: [
        new SortDefinition('publishedAt', 'published_at'),
        new SortDefinition('title', 'title'),
        new SortDefinition('id', 'id'),
    ],
    defaultSorts: ['-publishedAt', 'title'],
    maximumFilters: 10,
    maximumSorts: 3,
    maximumValuesPerFilter: 50,
    maximumStringLength: 255,
    tieBreakerSort: 'id',
);
```

Aliases are the public contract; columns and relation paths remain internal. Duplicate aliases, invalid identifiers or defaults, incompatible operators, undeclared enum values, unsafe sort relations, invalid null operators, and excessive relation depth fail during schema construction.

## Native inputs and consumer transport

`apply(Builder $query, FilterSchema $schema, array $filters = [], array $sorts = []): Builder` returns the supplied builder. Both arrays must be lists:

- Filters: `array{alias:string, operator:FilterOperator|string, value?:scalar|array|null}`. Every alias and operator must be declared. Values for `in`, `not_in` and `between` must be lists of native scalars. Other operators require a scalar value. Objects and nested arrays are rejected.
- Sorts: `array{alias:string, direction:'asc'|'desc'}`. Directions are exact lowercase strings. Every alias must be declared.

Unknown keys, malformed entries, missing required values and all complexity bounds fail before the builder is changed. A null-check may omit `value` or supply explicit null; other supplied null values fail the declared type. Empty filters retain caller predicates. Empty sorts use the schema defaults, with its tie-breaker appended once.

Consumers own HTTP syntax, scalar shorthand, comma splitting, frontend types and validation response mapping. Convert request input into the native lists above, then call the same applier used by jobs and CLI. A controller can catch `FilterableException` and throw `$exception->toValidationException()` after authorization. The package ships no HTTP parser or DTO compatibility aliases.

## Model trait

A host model may use `Nvl\Filterable\Traits\Filterable` and implement `filterSchema(): FilterSchema`. Call `Article::query()->applyFilters($filters, $sorts)` to use the same injected applier contract and validation. The scope never inspects a request.

## Types and operators

Supported value types are boolean, integer, decimal, string, enum, date, and date-time. Set and range behavior comes from `in`, `not_in`, and `between`.

Numeric strings, date-times, aliases and database paths must match their complete declared syntax; trailing newlines are rejected. Date-time timezone offsets require valid hours (`00`–`23`) and minutes (`00`–`59`) before conversion to UTC.

Supported operators are `equals`, `not_equals`, `contains`, `not_contains`, `in`, `not_in`, `between`, `before`, `after`, `gt`, `lt`, `gte`, `lte`, `is_null`, and `is_not_null`. A definition must explicitly allow each operator, and schema construction rejects operators that do not apply to the definition's value type.

Boolean parsing accepts only `true`, `false`, `1`, and `0`. Integers use strict base-10 syntax. Decimals reject locale separators and scientific notation and remain strings to avoid binary floating-point surprises. Dates require exact `YYYY-MM-DD` calendar values. Date-times require ISO-8601 instants with an explicit timezone and normalize to UTC database timestamps. Empty strings are rejected. Null operators require `nullable=true`.

`in` and `not_in` accept native lists and reject empty or oversized sets. `between` requires exactly two values. Custom handlers receive values after the same strict type normalization and own the complete predicate for their alias. Each handler runs inside a nested `where` group, so an ordinary `orWhere` remains inside the alias predicate and cannot broaden predicates already present on the caller's query.

Handlers are trusted, predicate-only callbacks. Keep them bounded and parameterized, and use them only for `where`, `orWhere`, `whereHas`, or equivalent predicate composition. Prepare joins and selected columns on the caller before filtering, and declare ordering through `SortDefinition`; query-shape mutations inside a nested handler are outside the callback contract. This grouping is a composition safeguard, not an authorization sandbox: a trusted callback can still use raw SQL or otherwise bypass application rules. Authorize the query before exposing results and test every custom handler's boundary.

Sort entries contain exactly `alias` and `direction`, with direction `asc` or `desc`. Duplicate and excessive sorts are rejected. When `tieBreakerSort` is declared, its ascending column is appended unless already present, which makes offset and cursor pagination deterministic.

## Database portability

`contains` and `not_contains` escape `%`, `_`, and the escape character, so user input always has literal substring semantics. Case, collation, and accent behavior follows the database column and driver. If an endpoint requires a different strategy, provide a custom handler and test it on every supported driver.

Date criteria target database DATE columns. A date comparison against a timestamp is not a whole-day predicate; use a trusted handler with explicit timezone/day boundaries for that behavior. Date-time criteria normalize to UTC, so store UTC instants and use a UTC database session for timestamp comparisons, or provide an explicit storage-aware handler. The applier does not infer a model cast's display timezone.

Negative operators retain SQL NULL semantics: `not_equals`, `not_in` and `not_contains` exclude NULL rows. Use `is_null` or a reviewed grouped custom predicate when the product needs to include missing values. A nullable definition permits explicit null checks; it does not change negative comparisons into null-inclusive predicates.

Never expose raw SQL, arbitrary JSON paths, request-provided relations, or request-provided columns.

## Custom handlers

A handler receives the nested Eloquent builder and a normalized native criterion:

```php
handler: static fn (Builder $query, array $criterion): Builder => $query
    ->where('name', $criterion['value'])
    ->orWhere('summary', $criterion['value']),
```

The criterion has `alias:string`, `operator:FilterOperator` and `value:mixed`, where the value follows the definition (date-time values become UTC `CarbonImmutable` instances). Handlers are trusted predicate-only callbacks. Their grouped predicates preserve the caller's constraints; authorization remains the consumer's responsibility.

## Testing your app

Replace `EloquentFilterApplierContract` in Laravel's container for an orchestration test. Early host bindings are retained, and later replacements affect subsequent resolutions and model scope calls.

```php
$query = (new Article)->newQuery();
$applier = Mockery::mock(EloquentFilterApplierContract::class);
$applier->shouldReceive('apply')->once()
    ->with($query, Mockery::type(FilterSchema::class), [], [])
    ->andReturn($query);
$this->app->instance(EloquentFilterApplierContract::class, $applier);
expect((new Article)->scopeApplyFilters($query))->toBe($query);
```

Use real host-owned rows for filtering correctness, alias and type rejection, literal wildcards, relations, custom OR predicates, complexity and deterministic pagination. Filterable has no package models or factories. Test database-specific case, collation and timestamp behavior on each supported production driver.

Public source declarations carry `@api`; helpers remain internal. Consumer DTOs and TypeScript generation belong to the application. See [UPGRADING.md](UPGRADING.md), [SECURITY.md](SECURITY.md) and [events](docs/events.md).

## Error codes and events

All recognized package failures implement `Nvl\Support\Contracts\PackageException`; only `RespondableException` opts into safe response metadata. Keep native PHP programmer errors and Laravel/SDK exceptions distinct. The optional `PackageExceptionRenderer` is registered by the host in `withExceptions`; it leaves unrelated, marker-only and non-JSON handling to the host. Its JSON envelope is `{message:string, code:string, context:object}`. Request locale is host-owned; diagnostics/previous exceptions are not public copy. Event schemas and source connections are documented in [events](docs/events.md).

The table lists enum discriminators, including any successful codes retained for compatibility. A code is not itself an HTTP status; the throwing exception's `suggestedStatus()` is authoritative, especially legacy/custom constructors. Empty context renders as `{}`; only documented JSON-safe context is presented.

| Code | Suggested status | Public context | Translation key |
| --- | --- | --- | --- |
| `operation_failed` | 422 | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.operation_failed` |
| `invalid_filter` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_filter` |
| `invalid_between_arity` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_between_arity` |
| `invalid_filter_value_shape` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_filter_value_shape` |
| `invalid_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_filter_value` |
| `invalid_boolean_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_boolean_filter_value` |
| `invalid_integer_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_integer_filter_value` |
| `invalid_decimal_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_decimal_filter_value` |
| `invalid_string_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_string_filter_value` |
| `invalid_enum_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_enum_filter_value` |
| `invalid_date_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_date_filter_value` |
| `invalid_date_time_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_date_time_filter_value` |
| `unknown_filter_alias` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.unknown_filter_alias` |
| `unknown_sort_alias` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.unknown_sort_alias` |
| `unknown_tie_breaker_sort` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.unknown_tie_breaker_sort` |
| `invalid_maximum_filters` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_maximum_filters` |
| `invalid_maximum_sorts` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_maximum_sorts` |
| `invalid_maximum_filter_values` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_maximum_filter_values` |
| `invalid_maximum_string_length` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_maximum_string_length` |
| `unknown_default_sort` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.unknown_default_sort` |
| `invalid_filter_schema` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_filter_schema` |
| `invalid_default_sort` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_default_sort` |
| `invalid_sort_alias` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_sort_alias` |
| `invalid_sort_column` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_sort_column` |
| `invalid_filter_alias` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_filter_alias` |
| `invalid_filter_column` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_filter_column` |
| `invalid_filter_operators` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_filter_operators` |
| `invalid_enum_values` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_enum_values` |
| `invalid_filter_shape` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_filter_shape` |
| `unsupported_filter_operator` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.unsupported_filter_operator` |
| `invalid_sort_shape` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_sort_shape` |
| `invalid_sort_direction` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.invalid_sort_direction` |
| `duplicate_filter_operator` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.duplicate_filter_operator` |
| `missing_enum_values` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.missing_enum_values` |
| `unexpected_enum_values` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.unexpected_enum_values` |
| `duplicate_enum_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.duplicate_enum_value` |
| `incompatible_filter_operator` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.incompatible_filter_operator` |
| `non_nullable_filter_operator` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.non_nullable_filter_operator` |
| `filter_complexity_exceeded` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.filter_complexity_exceeded` |
| `disallowed_filter_operator` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.disallowed_filter_operator` |
| `unexpected_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.unexpected_filter_value` |
| `missing_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.missing_filter_value` |
| `sort_complexity_exceeded` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.sort_complexity_exceeded` |
| `duplicate_sort` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.duplicate_sort` |
| `unsafe_wrapped_filter_column` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.unsafe_wrapped_filter_column` |
| `duplicate_default_sort` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.duplicate_default_sort` |
| `duplicate_filter_alias` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.duplicate_filter_alias` |
| `duplicate_sort_alias` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.duplicate_sort_alias` |
| `filter_value_complexity_exceeded` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.filter_value_complexity_exceeded` |
| `filter_string_too_long` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.filter_string_too_long` |
| `empty_string_filter_value` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-filterable::responsecode.empty_string_filter_value` |


## License

Released under the [MIT License](LICENSE).

Shared [service contracts, model extensions and composition](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/consumer-api.md) apply alongside the package-specific rules above.
