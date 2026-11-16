# Filterable consumer recipes

Use [installation](installation.md) for package setup and [API](api.md) for every constructor, operator and error code.

## Declare and apply a native schema

The following assumes the host owns `App\Models\Article` with `title`, `score`, `status` and `id` columns. The host authorizes article listing and applies its visibility constraints before passing `$query` here:

```php
use App\Models\Article;
use Nvl\Filterable\Contracts\EloquentFilterApplierContract;
use Nvl\Filterable\Definitions\FilterDefinition;
use Nvl\Filterable\Definitions\FilterSchema;
use Nvl\Filterable\Definitions\SortDefinition;
use Nvl\Filterable\Enums\FilterOperator;
use Nvl\Filterable\Enums\FilterValueType;

$schema = new FilterSchema(
    filters: [
        new FilterDefinition('title', 'title', operators: [FilterOperator::Contains]),
        new FilterDefinition('score', 'score', FilterValueType::Integer, [FilterOperator::Between]),
        new FilterDefinition('status', 'status', FilterValueType::Enum,
            [FilterOperator::In], enumValues: ['draft', 'published']),
    ],
    sorts: [new SortDefinition('id', 'id'), new SortDefinition('score', 'score')],
    defaultSorts: ['-score'],
    maximumFilters: 10,
    maximumSorts: 2,
    maximumValuesPerFilter: 20,
    maximumStringLength: 100,
    tieBreakerSort: 'id',
);
$query = Article::query()->where('status', 'published');
$query = app(EloquentFilterApplierContract::class)->apply($query, $schema, [
    ['alias' => 'title', 'operator' => 'contains', 'value' => '100%'],
    ['alias' => 'score', 'operator' => FilterOperator::Between, 'value' => [10, 50]],
], [
    ['alias' => 'score', 'direction' => 'desc'],
]);
$page = $query->paginate(25);
```

`100%` is a literal substring; `%`, `_` and escape characters do not become caller-controlled wildcard syntax. The returned builder retains the initial published predicate. Add the actual host authorization/ownership query before applying the schema; this example's status condition is an application choice, not an access-control implementation supplied by Filterable.

Defaults are `filters=[]`, `sorts=[]` at apply time. No filters means no added filtering; empty sorts activate the schema's defaults. Default sort aliases use an optional leading `-` for descending; runtime sort entries use exact lowercase `asc`/`desc`. A declared tie-breaker is appended ascending only if absent.

## Values, nulls and operators

Types are `Boolean`, `Integer`, `Decimal`, `String`, `Enum`, `Date`, `DateTime`. Declare only meaningful operators: equality/set/null checks are common; contains is string-specific; ordering/ranges apply to numbers/dates. See API for the exact allowed matrix.

Filter entries accept only `alias`, `operator`, `value`. `operator` is a `FilterOperator` or its exact backed string. Runtime lists must be PHP lists, not associative maps. `in`/`not_in` use scalar lists; `between` requires two entries. Unknown aliases, duplicate sorts, invalid directions and complexity violations throw `FilterableException` before any builder change.

A nullable field can declare `FilterOperator::IsNull` or `IsNotNull`. For those operators omit `value` or supply null; a non-null value is invalid. Other omitted values fail. Equality/set/range values must satisfy their declared scalar type; even a nullable definition does not make `equals` with null valid. Empty text is not interchangeable with null, and set/range lists cannot be empty.

Use `RequestParamsAdapter::parse($params, $schema)` to convert declared flat filter aliases, CSV lists and ranges, and sort maps or `alias:direction` strings. The compatibility flag `ignoreUnknown: true` skips unrelated parameters. A Date definition with `calendarDate: true` compares the date portion of a timestamp in the database session's timezone.

Boolean input accepts actual booleans, integers zero/one and exact strings `0`, `1`, `false`, `true`; it does not use general PHP truthiness. Integers accept native ints or canonical signed base-ten strings within native bounds. Decimals accept ints/floats/fixed-point strings whose string representation has no exponent/leading-zero shorthand and normalize to strings; this is query normalization, not an exact-money codec. Strings trim and must remain nonempty; enum text must match the declared list. Date strings must satisfy the package's strict format/range validation; datetime offsets require valid hours/minutes and normalize to UTC `CarbonImmutable`. Trailing newlines in aliases, paths and numeric/date strings reject. Keep request JSON parsing and any deliberate scalar/comma shorthand outside this service.

Defaults permit 25 filters, 5 sorts, 100 values/filter and 255 string bytes. Constructor bounds permit at most 100 filters, 25 sorts, 1,000 values/filter and 10,000 string bytes. These bound query complexity, not the final number of database rows; the host must paginate or stream the result.

## Reuse on a host model

Add `Nvl\Filterable\Traits\Filterable` to the host Eloquent model and implement:

```php
public function filterSchema(): \Nvl\Filterable\Definitions\FilterSchema
{
    return new \Nvl\Filterable\Definitions\FilterSchema(
        filters: [new \Nvl\Filterable\Definitions\FilterDefinition('title', 'title')],
        sorts: [new \Nvl\Filterable\Definitions\SortDefinition('id', 'id')],
        defaultSorts: ['id'],
    );
}
```

Then call `Article::query()->applyFilters($filters, $sorts)->paginate(25)`. Model filtering and direct applier calls use the same native contract. The host chooses model classes and authorization; no package model registration or DTO is required.

## Relations and custom predicates

A filter column may be a declared relation path, such as `author.name`, within the supported path-depth rules. Declare a real host relationship and test its driver semantics. Sort columns remain direct native columns. Never build a definition's column or relation path from request input.

A `FilterDefinition` can receive a closure handler with `(Builder $query, array $criterion): Builder`. The normalized criterion contains `alias`, enum `operator`, and normalized `value`; datetime values are UTC immutable instances. The applier invokes handlers on a grouped nested predicate builder, so ordinary `orWhere` does not bypass caller predicates. Keep the callback's SQL parameterized and do not execute the query or replace its authorization scope inside a handler.

Use `transform` for outer ordering or selection. An outer transform returns a derived builder with the caller's existing clauses and source intact. A `ModelMethodHandler` binds a declared public model method as a predicate or transform and passes the normalized value plus the operator string when the method declares a third parameter. For text aliases, use `allowWildcards: true` to opt into wildcard patterns or bind Core's `TextMatcher` to customize folding.

The applier is a write-free query capability: it has no transaction/revision/event/idempotency protocol. It does not supply actor or locale state. Host code may bind `EloquentFilterApplierContract` before provider registration for an alternate implementation; test that substitute's contract rather than silently changing transport shapes.

## Errors and focused verification

Catch `FilterableException` at the application's transport boundary and map its stable error code/field context. Test actual row results for scalar normalization, null checks, bounded sets, date offsets, literal wildcard values, relation predicates, custom OR grouping and deterministic pagination. Confirm invalid input does not partly mutate the builder.

Run affected host tests with `php artisan test --compact`. Exercise the production database driver for wildcard escaping and relation SQL. No Filterable config, migration or maintenance command exists; installation reference lists only the actual discovery/publishing surface. The package boots without Data, TypeScript Transformer or Ownership.
