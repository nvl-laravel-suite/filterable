# Public API: nvl/filterable

Generated from the package public contract. Use this reference to confirm fully qualified names, parameter order, defaults, native array shapes and return types. Read [usage](usage.md) for policy and operation semantics; declarations alone never grant authority.

Search for the relevant fully qualified service or contract and read only its matching symbol sections; this is a lookup catalog, not an instruction to load every declaration. `resolved` annotations expand imported aliases and domain type shapes. Framework inheritance and host-authored extensions remain outside this package contract.

## Symbols

- `Nvl\Filterable\Contracts\EloquentFilterApplierContract`
- `Nvl\Filterable\Definitions\FilterDefinition`
- `Nvl\Filterable\Definitions\FilterSchema`
- `Nvl\Filterable\Definitions\ModelMethodHandler`
- `Nvl\Filterable\Definitions\SortDefinition`
- `Nvl\Filterable\Enums\FilterOperator`
- `Nvl\Filterable\Enums\FilterValueType`
- `Nvl\Filterable\Enums\FilterableResponseCode`
- `Nvl\Filterable\Enums\SortDirection`
- `Nvl\Filterable\Exceptions\FilterableException`
- `Nvl\Filterable\Input\FilterInput`
- `Nvl\Filterable\Input\RequestParamsAdapter`
- `Nvl\Filterable\Services\EloquentFilterApplier`
- `Nvl\Filterable\Traits\Filterable`

## Nvl\Filterable\Contracts\EloquentFilterApplierContract

```text
interface Nvl\Filterable\Contracts\EloquentFilterApplierContract
public abstract function apply(Illuminate\Database\Eloquent\Builder $query, Nvl\Filterable\Definitions\FilterSchema $schema, array $filters=[], array $sorts=[]): Illuminate\Database\Eloquent\Builder
@template TModel of Model
resolved @template TModel of Illuminate\Database\Eloquent\Model
@param Builder<TModel> $query
resolved @param Illuminate\Database\Eloquent\Builder<TModel> $query
@param array<array-key, mixed> $filters
@param array<array-key, mixed> $sorts
@return Builder<TModel>
resolved @return Illuminate\Database\Eloquent\Builder<TModel>
template Nvl\Filterable\Contracts\EloquentFilterApplierContract::TModel of Illuminate\Database\Eloquent\Model
```

## Nvl\Filterable\Definitions\FilterDefinition

```text
final readonly class Nvl\Filterable\Definitions\FilterDefinition
public function __construct(string $alias, string $column, Nvl\Filterable\Enums\FilterValueType $type=\Nvl\Filterable\Enums\FilterValueType::String, array $operators=[\Nvl\Filterable\Enums\FilterOperator::Equals], bool $nullable=false, array $enumValues=[], Closure|Nvl\Filterable\Definitions\ModelMethodHandler|null $handler=null, ?int $minimum=null, ?int $maximum=null, bool $allowWildcards=false, bool $calendarDate=false, Closure|Nvl\Filterable\Definitions\ModelMethodHandler|null $transform=null): mixed
@param non-empty-string $alias
@param non-empty-string $column
@param array<array-key, mixed> $operators
@param array<array-key, mixed> $enumValues
@param (Closure(\Illuminate\Database\Eloquent\Builder<*>, array{alias: string, operator: FilterOperator, value: mixed}): (\Illuminate\Database\Eloquent\Builder<*> | void) | ModelMethodHandler | null) $handler
resolved @param (Closure(Illuminate\Database\Eloquent\Builder<*>, array{alias: string, operator: Nvl\Filterable\Enums\FilterOperator, value: mixed}): (Illuminate\Database\Eloquent\Builder<*> | void) | Nvl\Filterable\Definitions\ModelMethodHandler | null) $handler
@param (Closure(\Illuminate\Database\Eloquent\Builder<*>, array{alias: string, operator: FilterOperator, value: mixed}): \Illuminate\Database\Eloquent\Builder<*> | ModelMethodHandler | null) $transform
resolved @param (Closure(Illuminate\Database\Eloquent\Builder<*>, array{alias: string, operator: Nvl\Filterable\Enums\FilterOperator, value: mixed}): Illuminate\Database\Eloquent\Builder<*> | Nvl\Filterable\Definitions\ModelMethodHandler | null) $transform
@param (int | null) $minimum
@param (int | null) $maximum
public readonly string $alias
@param non-empty-string $alias
public readonly bool $allowWildcards
public readonly bool $calendarDate
public readonly string $column
@param non-empty-string $column
public readonly array $enumValues
@var list<string>
public readonly Closure|Nvl\Filterable\Definitions\ModelMethodHandler|null $handler
@param (Closure(\Illuminate\Database\Eloquent\Builder<*>, array{alias: string, operator: FilterOperator, value: mixed}): (\Illuminate\Database\Eloquent\Builder<*> | void) | ModelMethodHandler | null) $handler
resolved @param (Closure(Illuminate\Database\Eloquent\Builder<*>, array{alias: string, operator: Nvl\Filterable\Enums\FilterOperator, value: mixed}): (Illuminate\Database\Eloquent\Builder<*> | void) | Nvl\Filterable\Definitions\ModelMethodHandler | null) $handler
public readonly ?int $maximum
@param (int | null) $maximum
public readonly ?int $minimum
@param (int | null) $minimum
public readonly bool $nullable
public readonly array $operators
@var list<FilterOperator>
resolved @var list<Nvl\Filterable\Enums\FilterOperator>
public readonly Closure|Nvl\Filterable\Definitions\ModelMethodHandler|null $transform
@param (Closure(\Illuminate\Database\Eloquent\Builder<*>, array{alias: string, operator: FilterOperator, value: mixed}): \Illuminate\Database\Eloquent\Builder<*> | ModelMethodHandler | null) $transform
resolved @param (Closure(Illuminate\Database\Eloquent\Builder<*>, array{alias: string, operator: Nvl\Filterable\Enums\FilterOperator, value: mixed}): Illuminate\Database\Eloquent\Builder<*> | Nvl\Filterable\Definitions\ModelMethodHandler | null) $transform
public readonly Nvl\Filterable\Enums\FilterValueType $type
```

## Nvl\Filterable\Definitions\FilterSchema

```text
final readonly class Nvl\Filterable\Definitions\FilterSchema
public function __construct(array $filters, array $sorts, array $defaultSorts=[], int $maximumFilters=25, int $maximumSorts=5, int $maximumValuesPerFilter=100, int $maximumStringLength=255, ?string $tieBreakerSort=null): mixed
@param array<array-key, mixed> $filters
@param array<array-key, mixed> $sorts
@param array<array-key, mixed> $defaultSorts
public function filter(string $alias): ?Nvl\Filterable\Definitions\FilterDefinition
public function sort(string $alias): ?Nvl\Filterable\Definitions\SortDefinition
public readonly array $defaultSorts
@var list<string>
public readonly array $filters
@var list<FilterDefinition>
resolved @var list<Nvl\Filterable\Definitions\FilterDefinition>
public readonly int $maximumFilters
public readonly int $maximumSorts
public readonly int $maximumStringLength
public readonly int $maximumValuesPerFilter
public readonly array $sorts
@var list<SortDefinition>
resolved @var list<Nvl\Filterable\Definitions\SortDefinition>
public readonly ?string $tieBreakerSort
```

## Nvl\Filterable\Definitions\ModelMethodHandler

```text
final readonly class Nvl\Filterable\Definitions\ModelMethodHandler
public function __construct(string $method): mixed
public function __invoke(Illuminate\Database\Eloquent\Builder $query, array $criterion): Illuminate\Database\Eloquent\Builder
@template TModel of Model
resolved @template TModel of Illuminate\Database\Eloquent\Model
@param Builder<TModel> $query
resolved @param Illuminate\Database\Eloquent\Builder<TModel> $query
@param array{alias: string, operator: FilterOperator, value: mixed} $criterion
resolved @param array{alias: string, operator: Nvl\Filterable\Enums\FilterOperator, value: mixed} $criterion
@return Builder<*>
resolved @return Illuminate\Database\Eloquent\Builder<*>
template Nvl\Filterable\Definitions\ModelMethodHandler::TModel of Illuminate\Database\Eloquent\Model
public readonly string $method
```

## Nvl\Filterable\Definitions\SortDefinition

```text
final readonly class Nvl\Filterable\Definitions\SortDefinition
public function __construct(string $alias, string $column): mixed
@param non-empty-string $alias
@param non-empty-string $column
public readonly string $alias
@param non-empty-string $alias
public readonly string $column
@param non-empty-string $column
```

## Nvl\Filterable\Enums\FilterOperator

```text
enum Nvl\Filterable\Enums\FilterOperator implements BackedEnum, UnitEnum: string
public const Nvl\Filterable\Enums\FilterOperator After=Nvl\Filterable\Enums\FilterOperator::After="after"
public const Nvl\Filterable\Enums\FilterOperator Before=Nvl\Filterable\Enums\FilterOperator::Before="before"
public const Nvl\Filterable\Enums\FilterOperator Between=Nvl\Filterable\Enums\FilterOperator::Between="between"
public const Nvl\Filterable\Enums\FilterOperator Contains=Nvl\Filterable\Enums\FilterOperator::Contains="contains"
public const Nvl\Filterable\Enums\FilterOperator Equals=Nvl\Filterable\Enums\FilterOperator::Equals="equals"
public const Nvl\Filterable\Enums\FilterOperator Gt=Nvl\Filterable\Enums\FilterOperator::Gt="gt"
public const Nvl\Filterable\Enums\FilterOperator Gte=Nvl\Filterable\Enums\FilterOperator::Gte="gte"
public const Nvl\Filterable\Enums\FilterOperator In=Nvl\Filterable\Enums\FilterOperator::In="in"
public const Nvl\Filterable\Enums\FilterOperator IsNotNull=Nvl\Filterable\Enums\FilterOperator::IsNotNull="is_not_null"
public const Nvl\Filterable\Enums\FilterOperator IsNull=Nvl\Filterable\Enums\FilterOperator::IsNull="is_null"
public const Nvl\Filterable\Enums\FilterOperator Lt=Nvl\Filterable\Enums\FilterOperator::Lt="lt"
public const Nvl\Filterable\Enums\FilterOperator Lte=Nvl\Filterable\Enums\FilterOperator::Lte="lte"
public const Nvl\Filterable\Enums\FilterOperator NotContains=Nvl\Filterable\Enums\FilterOperator::NotContains="not_contains"
public const Nvl\Filterable\Enums\FilterOperator NotEquals=Nvl\Filterable\Enums\FilterOperator::NotEquals="not_equals"
public const Nvl\Filterable\Enums\FilterOperator NotIn=Nvl\Filterable\Enums\FilterOperator::NotIn="not_in"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Filterable\Enums\FilterValueType

```text
enum Nvl\Filterable\Enums\FilterValueType implements BackedEnum, UnitEnum: string
public const Nvl\Filterable\Enums\FilterValueType Boolean=Nvl\Filterable\Enums\FilterValueType::Boolean="boolean"
public const Nvl\Filterable\Enums\FilterValueType Date=Nvl\Filterable\Enums\FilterValueType::Date="date"
public const Nvl\Filterable\Enums\FilterValueType DateTime=Nvl\Filterable\Enums\FilterValueType::DateTime="date_time"
public const Nvl\Filterable\Enums\FilterValueType Decimal=Nvl\Filterable\Enums\FilterValueType::Decimal="decimal"
public const Nvl\Filterable\Enums\FilterValueType Enum=Nvl\Filterable\Enums\FilterValueType::Enum="enum"
public const Nvl\Filterable\Enums\FilterValueType Integer=Nvl\Filterable\Enums\FilterValueType::Integer="integer"
public const Nvl\Filterable\Enums\FilterValueType String=Nvl\Filterable\Enums\FilterValueType::String="string"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Filterable\Enums\FilterableResponseCode

```text
enum Nvl\Filterable\Enums\FilterableResponseCode implements BackedEnum, Nvl\Support\Contracts\ResponseCode, UnitEnum: string
public const Nvl\Filterable\Enums\FilterableResponseCode DisallowedFilterOperator=Nvl\Filterable\Enums\FilterableResponseCode::DisallowedFilterOperator="disallowed_filter_operator"
public const Nvl\Filterable\Enums\FilterableResponseCode DuplicateDefaultSort=Nvl\Filterable\Enums\FilterableResponseCode::DuplicateDefaultSort="duplicate_default_sort"
public const Nvl\Filterable\Enums\FilterableResponseCode DuplicateEnumValue=Nvl\Filterable\Enums\FilterableResponseCode::DuplicateEnumValue="duplicate_enum_value"
public const Nvl\Filterable\Enums\FilterableResponseCode DuplicateFilterAlias=Nvl\Filterable\Enums\FilterableResponseCode::DuplicateFilterAlias="duplicate_filter_alias"
public const Nvl\Filterable\Enums\FilterableResponseCode DuplicateFilterOperator=Nvl\Filterable\Enums\FilterableResponseCode::DuplicateFilterOperator="duplicate_filter_operator"
public const Nvl\Filterable\Enums\FilterableResponseCode DuplicateSort=Nvl\Filterable\Enums\FilterableResponseCode::DuplicateSort="duplicate_sort"
public const Nvl\Filterable\Enums\FilterableResponseCode DuplicateSortAlias=Nvl\Filterable\Enums\FilterableResponseCode::DuplicateSortAlias="duplicate_sort_alias"
public const Nvl\Filterable\Enums\FilterableResponseCode EmptyStringFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::EmptyStringFilterValue="empty_string_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode FilterComplexityExceeded=Nvl\Filterable\Enums\FilterableResponseCode::FilterComplexityExceeded="filter_complexity_exceeded"
public const Nvl\Filterable\Enums\FilterableResponseCode FilterStringTooLong=Nvl\Filterable\Enums\FilterableResponseCode::FilterStringTooLong="filter_string_too_long"
public const Nvl\Filterable\Enums\FilterableResponseCode FilterValueComplexityExceeded=Nvl\Filterable\Enums\FilterableResponseCode::FilterValueComplexityExceeded="filter_value_complexity_exceeded"
public const Nvl\Filterable\Enums\FilterableResponseCode IncompatibleFilterOperator=Nvl\Filterable\Enums\FilterableResponseCode::IncompatibleFilterOperator="incompatible_filter_operator"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidBetweenArity=Nvl\Filterable\Enums\FilterableResponseCode::InvalidBetweenArity="invalid_between_arity"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidBooleanFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::InvalidBooleanFilterValue="invalid_boolean_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidDateFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::InvalidDateFilterValue="invalid_date_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidDateTimeFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::InvalidDateTimeFilterValue="invalid_date_time_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidDecimalFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::InvalidDecimalFilterValue="invalid_decimal_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidDefaultSort=Nvl\Filterable\Enums\FilterableResponseCode::InvalidDefaultSort="invalid_default_sort"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidEnumFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::InvalidEnumFilterValue="invalid_enum_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidEnumValues=Nvl\Filterable\Enums\FilterableResponseCode::InvalidEnumValues="invalid_enum_values"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilter=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilter="invalid_filter"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilterAlias=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilterAlias="invalid_filter_alias"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilterColumn=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilterColumn="invalid_filter_column"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilterHandler=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilterHandler="invalid_filter_handler"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilterOperators=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilterOperators="invalid_filter_operators"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilterSchema=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilterSchema="invalid_filter_schema"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilterShape=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilterShape="invalid_filter_shape"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilterTransform=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilterTransform="invalid_filter_transform"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilterValue="invalid_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidFilterValueShape=Nvl\Filterable\Enums\FilterableResponseCode::InvalidFilterValueShape="invalid_filter_value_shape"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidIntegerFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::InvalidIntegerFilterValue="invalid_integer_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidMaximumFilterValues=Nvl\Filterable\Enums\FilterableResponseCode::InvalidMaximumFilterValues="invalid_maximum_filter_values"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidMaximumFilters=Nvl\Filterable\Enums\FilterableResponseCode::InvalidMaximumFilters="invalid_maximum_filters"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidMaximumSorts=Nvl\Filterable\Enums\FilterableResponseCode::InvalidMaximumSorts="invalid_maximum_sorts"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidMaximumStringLength=Nvl\Filterable\Enums\FilterableResponseCode::InvalidMaximumStringLength="invalid_maximum_string_length"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidSortAlias=Nvl\Filterable\Enums\FilterableResponseCode::InvalidSortAlias="invalid_sort_alias"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidSortColumn=Nvl\Filterable\Enums\FilterableResponseCode::InvalidSortColumn="invalid_sort_column"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidSortDirection=Nvl\Filterable\Enums\FilterableResponseCode::InvalidSortDirection="invalid_sort_direction"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidSortShape=Nvl\Filterable\Enums\FilterableResponseCode::InvalidSortShape="invalid_sort_shape"
public const Nvl\Filterable\Enums\FilterableResponseCode InvalidStringFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::InvalidStringFilterValue="invalid_string_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode MissingEnumValues=Nvl\Filterable\Enums\FilterableResponseCode::MissingEnumValues="missing_enum_values"
public const Nvl\Filterable\Enums\FilterableResponseCode MissingFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::MissingFilterValue="missing_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode NonNullableFilterOperator=Nvl\Filterable\Enums\FilterableResponseCode::NonNullableFilterOperator="non_nullable_filter_operator"
public const Nvl\Filterable\Enums\FilterableResponseCode OperationFailed=Nvl\Filterable\Enums\FilterableResponseCode::OperationFailed="operation_failed"
public const Nvl\Filterable\Enums\FilterableResponseCode SortComplexityExceeded=Nvl\Filterable\Enums\FilterableResponseCode::SortComplexityExceeded="sort_complexity_exceeded"
public const Nvl\Filterable\Enums\FilterableResponseCode UnexpectedEnumValues=Nvl\Filterable\Enums\FilterableResponseCode::UnexpectedEnumValues="unexpected_enum_values"
public const Nvl\Filterable\Enums\FilterableResponseCode UnexpectedFilterValue=Nvl\Filterable\Enums\FilterableResponseCode::UnexpectedFilterValue="unexpected_filter_value"
public const Nvl\Filterable\Enums\FilterableResponseCode UnknownDefaultSort=Nvl\Filterable\Enums\FilterableResponseCode::UnknownDefaultSort="unknown_default_sort"
public const Nvl\Filterable\Enums\FilterableResponseCode UnknownFilterAlias=Nvl\Filterable\Enums\FilterableResponseCode::UnknownFilterAlias="unknown_filter_alias"
public const Nvl\Filterable\Enums\FilterableResponseCode UnknownSortAlias=Nvl\Filterable\Enums\FilterableResponseCode::UnknownSortAlias="unknown_sort_alias"
public const Nvl\Filterable\Enums\FilterableResponseCode UnknownTieBreakerSort=Nvl\Filterable\Enums\FilterableResponseCode::UnknownTieBreakerSort="unknown_tie_breaker_sort"
public const Nvl\Filterable\Enums\FilterableResponseCode UnsafeWrappedFilterColumn=Nvl\Filterable\Enums\FilterableResponseCode::UnsafeWrappedFilterColumn="unsafe_wrapped_filter_column"
public const Nvl\Filterable\Enums\FilterableResponseCode UnsupportedFilterOperator=Nvl\Filterable\Enums\FilterableResponseCode::UnsupportedFilterOperator="unsupported_filter_operator"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Filterable\Enums\SortDirection

```text
enum Nvl\Filterable\Enums\SortDirection implements BackedEnum, UnitEnum: string
public const Nvl\Filterable\Enums\SortDirection Asc=Nvl\Filterable\Enums\SortDirection::Asc="asc"
public const Nvl\Filterable\Enums\SortDirection Desc=Nvl\Filterable\Enums\SortDirection::Desc="desc"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Filterable\Exceptions\FilterableException

```text
final class Nvl\Filterable\Exceptions\FilterableException extends Exception implements Nvl\Support\Contracts\RespondableException
public function __construct(string $message, string $errorCode="invalid_filter", string $path="filter", ?Throwable $previous=null): mixed
public static function because(Nvl\Filterable\Enums\FilterableResponseCode $code, string $diagnosticMessage, string $path="filter", ?Throwable $previous=null): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function toValidationException(): Illuminate\Validation\ValidationException
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
public readonly string $errorCode
public readonly string $path
```

## Nvl\Filterable\Input\FilterInput

```text
final readonly class Nvl\Filterable\Input\FilterInput
public function __construct(array $filters, array $sorts): mixed
@param list<array{alias: string, operator: (FilterOperator | string), value?: mixed}> $filters
resolved @param list<array{alias: string, operator: (Nvl\Filterable\Enums\FilterOperator | string), value?: mixed}> $filters
@param list<array{alias: string, direction: string}> $sorts
public readonly array $filters
@param list<array{alias: string, operator: (FilterOperator | string), value?: mixed}> $filters
resolved @param list<array{alias: string, operator: (Nvl\Filterable\Enums\FilterOperator | string), value?: mixed}> $filters
public readonly array $sorts
@param list<array{alias: string, direction: string}> $sorts
```

## Nvl\Filterable\Input\RequestParamsAdapter

```text
final readonly class Nvl\Filterable\Input\RequestParamsAdapter
public function __construct(Nvl\Filterable\Services\FilterCriterionNormalizer $normalizer): mixed
public function parse(array $params, Nvl\Filterable\Definitions\FilterSchema $schema, bool $ignoreUnknown=false): Nvl\Filterable\Input\FilterInput
@param array<array-key, mixed> $params
```

## Nvl\Filterable\Services\EloquentFilterApplier

```text
final readonly class Nvl\Filterable\Services\EloquentFilterApplier implements Nvl\Filterable\Contracts\EloquentFilterApplierContract
public function __construct(Nvl\Filterable\Services\FilterCriterionNormalizer $normalizer, ?Nvl\Support\Contracts\TextMatcher $textMatcher=null): mixed
public function apply(Illuminate\Database\Eloquent\Builder $query, Nvl\Filterable\Definitions\FilterSchema $schema, array $filters=[], array $sorts=[]): Illuminate\Database\Eloquent\Builder
@template TModel of Model
resolved @template TModel of Illuminate\Database\Eloquent\Model
@param Builder<TModel> $query
resolved @param Illuminate\Database\Eloquent\Builder<TModel> $query
@param array<array-key, mixed> $filters
@param array<array-key, mixed> $sorts
@return Builder<TModel>
resolved @return Illuminate\Database\Eloquent\Builder<TModel>
template Nvl\Filterable\Services\EloquentFilterApplier::TModel of Illuminate\Database\Eloquent\Model
```

## Nvl\Filterable\Traits\Filterable

```text
trait Nvl\Filterable\Traits\Filterable
@template TModel of Model
resolved @template TModel of Illuminate\Database\Eloquent\Model
public abstract function filterSchema(): Nvl\Filterable\Definitions\FilterSchema
public function scopeApplyFilters(Illuminate\Database\Eloquent\Builder $query, array $filters=[], array $sorts=[]): Illuminate\Database\Eloquent\Builder
@param Builder<TModel> $query
resolved @param Illuminate\Database\Eloquent\Builder<TModel> $query
@param array<array-key, mixed> $filters
@param array<array-key, mixed> $sorts
@return Builder<TModel>
resolved @return Illuminate\Database\Eloquent\Builder<TModel>
template Nvl\Filterable\Traits\Filterable::TModel of Illuminate\Database\Eloquent\Model
```
