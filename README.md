# NVL Filterable — API and usage

## Quickstart

```sh
composer require nvl/filterable:^5.0
php artisan nvl:install filterable --dry-run
php artisan nvl:install filterable
```

Required NVL dependencies: `nvl/core` (`^5.0`). Declare an allowlisted FilterSchema and parse validated filters into FilterSet. The query must be for a host-owned model; filters are not authorization.
Review the published common config, select one migration owner, and run schema preflight before existing-table upgrades. The installer does not enable features or run migrations. Follow the detailed installation and capability sections below before invoking a storage/provider operation.

Inject `Nvl\Filterable\Contracts\EloquentFilterApplierContract` in a host service. After supplying the trusted inputs described above, the first public call is:

```php
use Nvl\Filterable\Contracts\EloquentFilterApplierContract;

/** @var EloquentFilterApplierContract $capability */
$result = $capability->apply($hostQuery, $filters, $schema);
```

Use the [event catalog](docs/events.md) and [Testing your app](#testing-your-app) below. The suite [getting-started guide](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/getting-started.md) provides a complete Comments host fixture; package archives retain their own local references.


[← NVL Laravel Suite](https://github.com/nvl-laravel-suite)

For support, [open an issue](https://github.com/nvl-laravel-suite/filterable/issues). For vulnerabilities, use
[private reporting](https://github.com/nvl-laravel-suite/filterable/security/advisories/new). See [Contributing](CONTRIBUTING.md).

See the [installation and publishing guide](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/installation.md) for Composer setup, configuration, migration ownership, and agent skills.

## Quick reference

| Item | Value |
|---|---|
| Installed through | `composer require nvl/filterable:^5.0` |
| Module identifier | `nvl/filterable` |
| PHP namespace | `Nvl\Filterable` |
| Service provider | `Nvl\Filterable\Providers\FilterableServiceProvider` |
| Configuration | None; schemas are declared in application code |

## Purpose

`nvl/filterable` translates explicit typed filter sets into allowlisted Eloquent predicates and sorting on Laravel 12–13 and PHP 8.4+. It is not a facets engine, full-text search engine, authorization layer, or arbitrary query language.

The package depends only on `nvl/core` inside the NVL family. It has no migrations, routes, configuration, or host-model assumptions.

## Requirements and installation

```bash
composer require nvl/filterable:^5.0
php artisan vendor:publish --tag=nvl-filterable-translations
php artisan vendor:publish --tag=nvl-filterable-skills
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

## Build a transport-neutral filter set

Programmatic callers construct `FilterSet`, `FilterCriterion`, and `SortCriterion` directly. HTTP callers use the isolated adapter:

```php
use Nvl\Filterable\Http\QueryFilterSetFactory;
use Nvl\Filterable\Contracts\EloquentFilterApplierContract;

$filterSet = app(QueryFilterSetFactory::class)->fromHttpQuery(
    $request->query(),
    $schema,
);

$query = app(EloquentFilterApplierContract::class)->apply(
    Article::query(),
    $filterSet,
    $schema,
);

$articles = $query->paginate();
```

Expected HTTP shape:

```text
?filter[status][operator]=in&filter[status][value]=draft,published
&filter[author][operator]=contains&filter[author][value]=Ada
&sort=-publishedAt,title
```

The adapter rejects unknown aliases, malformed filter objects, unsupported operators, invalid sort values, and excessive filter count. It never silently forwards unknown input.

Scalar shorthand always means `equals`:

```text
?filter[status]=draft
```

Explicit objects contain exactly `operator` and, except for null checks, `value`. `is_null` and `is_not_null` must omit `value`:

```text
?filter[publishedAt][operator]=is_null
```

Use `fromQuery()` outside an HTTP boundary when the caller should receive `FilterableException` directly. `fromHttpQuery()` converts malformed input to Laravel's standard 422 validation response.

## Model trait

A model may use `Filterable` and return an immutable schema:

```php
use Nvl\Filterable\Data\FilterSet;
use Nvl\Filterable\Traits\Filterable;

final class Article extends Model
{
    use Filterable;

    public function filterSchema(): FilterSchema
    {
        return ArticleFilters::schema();
    }
}

$articles = Article::query()
    ->applyFilterSet($filterSet)
    ->paginate();
```

The trait accepts a `FilterSet`; it does not inspect the Request facade.

## Types and operators

Supported value types are boolean, integer, decimal, string, enum, date, and date-time. Set and range behavior comes from `in`, `not_in`, and `between`.

Supported operators are `equals`, `not_equals`, `contains`, `not_contains`, `in`, `not_in`, `between`, `before`, `after`, `gt`, `lt`, `gte`, `lte`, `is_null`, and `is_not_null`. A definition must explicitly allow each operator, and schema construction rejects operators that do not apply to the definition's value type.

Boolean parsing accepts only `true`, `false`, `1`, and `0`. Integers use strict base-10 syntax. Decimals reject locale separators and scientific notation and remain strings to avoid binary floating-point surprises. Dates require exact `YYYY-MM-DD` calendar values. Date-times require ISO-8601 instants with an explicit timezone and normalize to UTC database timestamps. Empty strings are rejected. Null operators require `nullable=true`.

`in` and `not_in` accept a list or a comma-separated string and reject empty or oversized sets. `between` requires exactly two values. Custom handlers receive values after the same strict type normalization and own the complete predicate for their alias. Each handler runs inside a nested `where` group, so an ordinary `orWhere` remains inside the alias predicate and cannot broaden predicates already present on the caller's query.

Handlers are trusted, predicate-only callbacks. Keep them bounded and parameterized, and use them only for `where`, `orWhere`, `whereHas`, or equivalent predicate composition. Prepare joins and selected columns on the caller before filtering, and declare ordering through `SortDefinition`; query-shape mutations inside a nested handler are outside the callback contract. This grouping is a composition safeguard, not an authorization sandbox: a trusted callback can still use raw SQL or otherwise bypass application rules. Authorize the query before exposing results and test every custom handler's boundary.

`SortCriterion` uses the `SortDirection` enum. HTTP sorts use `sort=-publishedAt,title` or an alias-to-direction object. Duplicate and excessive sorts are rejected. When `tieBreakerSort` is declared, its ascending column is appended unless already present, which makes offset and cursor pagination deterministic.

## Database portability

`contains` and `not_contains` escape `%`, `_`, and the escape character, so user input always has literal substring semantics. Case, collation, and accent behavior follows the database column and driver. If an endpoint requires a different strategy, provide a custom handler and test it on every supported driver.

Never expose raw SQL, arbitrary JSON paths, request-provided relations, or request-provided columns.

## TypeScript

Filter DTOs and enums generate under `Nvl.Filterable.*`:

```bash
php artisan nvl:data:types:generate
php artisan nvl:data:types:check
```

## Failure behavior

Contract and input violations throw `FilterableException` with stable `errorCode` and `path` context before unsafe identifiers reach Eloquent. Query execution errors remain database exceptions. Controllers should call `fromHttpQuery()` after authorization for Laravel's safe 422 validation response.

## Verification

Tests cover SQL injection payloads, aliases, relations, custom handlers, null and empty input, booleans, decimals, enum sets, ranges, dates and time zones, sorting, complexity limits, and SQLite/PostgreSQL/MySQL parity.

See [UPGRADING.md](UPGRADING.md), [SECURITY.md](SECURITY.md), [CONTRIBUTING.md](CONTRIBUTING.md), and [CHANGELOG.md](CHANGELOG.md).

## Supported PHP usage

The source `@api` declarations identify supported workflows, extension contracts, and value types. Public members marked `@internal` and untagged implementation types remain package-owned. Concrete Actions retain their existing constructors, qualifiers, and `execute()` signatures.

A package model returned or accepted by a public workflow is an identity/result handle. Use its declared type and `getKey()`, `getKeyName()`, `getMorphClass()`, `getRouteKey()`, `getRouteKeyName()`, `is()`, `isNot()`, and `relationLoaded()`. Read only explicitly declared in-memory `@nvl-consumer-read` fields; ordinary model PHPDocs and fillable attributes do not grant consumer reads. Obtain display projections through public reads. Persistence, additional model queries, relation access/loading, and generic model serialization are outside this contract. Host-model queries remain available, while traversal or aggregates of package capability relations require the package public reader or authorized adapter.

## Testing your app

Inject `EloquentFilterApplierContract` when application services apply a
`FilterSet`. The native applier and its interface default share the existing
singleton lifetime. The public `Filterable::scopeApplyFilterSet` adapter also
resolves this contract, so a host replacement is used by the real model scope.
`QueryFilterSetFactory`, filter schemas, typed criteria, and immutable values
remain direct APIs.

For a host-owned `Article` model that uses `Filterable`, a scope substitution test
can retain its actual Eloquent builder without executing a query:

```php
use App\Models\Article;
use Nvl\Filterable\Contracts\EloquentFilterApplierContract;
use Nvl\Filterable\Data\FilterSet;

$model = new Article;
$query = $model->newQuery();
$filters = FilterSet::none();
$applier = Mockery::mock(EloquentFilterApplierContract::class);
$applier->shouldReceive('apply')->once()
    ->with($query, $filters, Mockery::type(\Nvl\Filterable\Definitions\FilterSchema::class))
    ->andReturn($query);
$this->app->instance(EloquentFilterApplierContract::class, $applier);
expect($model->scopeApplyFilterSet($query, $filters))->toBe($query);
```

Filterable has no package models or factories. Use the host application's model
factory when persisted host rows are required. Test schema/parser/value behavior
directly and test the real applier with host-owned database rows to verify
normalization, authorization predicates, operator semantics, and sorting.
A substitute is evidence of your orchestration, not filtering correctness.

In Laravel application tests, register a native Mockery interface mock or a small
implementation with `$this->app->instance(Contract::class, $substitute)` before
resolving your application service. A host binding installed before package
registration is retained; later contract replacements affect subsequent
resolutions. Rebuild previously resolved host services after replacing their
dependencies. Concrete implementations remain callable with their original
constructors through major 5. Mocks exercise your application orchestration;
package authorization, persistence, and external effects need real integration
tests.

The existing model scope now resolves EloquentFilterApplierContract. Its singleton default resolves the native concrete through the supplied container; parsing/schema APIs remain direct.

For static consumer checks, include the shipped
[`consumer-audit.neon`](https://github.com/nvl-laravel-suite/core/blob/main/support/consumer-audit.neon) from
`vendor/nvl/core/support/consumer-audit.neon` in your host PHPStan configuration
and configure explicit `nvlConsumer.testPaths` for factory-backed tests. The
extension checks supported APIs and model/query boundaries; it does not prove
authorization or arbitrary dynamic SQL.

## Testing your app

Inject the supported contract rather than constructing its concrete Action or querying package tables. Replace `Nvl\Filterable\Contracts\EloquentFilterApplierContract` in Laravel's native container for a host-workflow test:

```php
use Nvl\Filterable\Contracts\EloquentFilterApplierContract;

$double = Mockery::mock(EloquentFilterApplierContract::class);
$this->app->instance(EloquentFilterApplierContract::class, $double);
// Configure the exact apply arguments and documented return value for your host case.
```

The package's conditional native binding preserves host substitutions. Production uses the real contract; test doubles do not prove its storage/authorization behavior.

This package has no persistent fixture model in the supported factory inventory. Test value objects and contract inputs directly; do not invent a package model factory.

Use Laravel `Event::fake()`, `Queue::fake()`, `Mail::fake()` or `Storage::fake()` only for the effects the host test intends to isolate. Use real commits/listeners for timing proof. Add the optional Core consumer boundary rules to host PHPStan:

```neon
includes:
    - vendor/nvl/core/support/consumer-audit.neon
parameters:
    nvlConsumer:
        testPaths: [tests]
        tableNames: []
        exceptions: []
```

Rules read installed public metadata without suite boot. They flag internal symbols, package model queries/writes, capability relations and owned tables; they cannot prove dynamic code or runtime authorization. Exact exceptions require `file`, `identifier`, `symbol`, and a documented `reason`. New C3/C4/E tests, archives and guide execution remain pending until the integration phase records results.

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
