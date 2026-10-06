<?php

declare(strict_types=1);

namespace Nvl\Filterable\Enums;

use Nvl\Support\Contracts\ResponseCode;

/** Stable public response codes for Filterable.
 * @api
 */
enum FilterableResponseCode: string implements ResponseCode
{
    case OperationFailed = 'operation_failed';
    case InvalidFilter = 'invalid_filter';
    case InvalidBetweenArity = 'invalid_between_arity';
    case InvalidFilterValueShape = 'invalid_filter_value_shape';
    case InvalidFilterValue = 'invalid_filter_value';
    case InvalidBooleanFilterValue = 'invalid_boolean_filter_value';
    case InvalidIntegerFilterValue = 'invalid_integer_filter_value';
    case InvalidDecimalFilterValue = 'invalid_decimal_filter_value';
    case InvalidStringFilterValue = 'invalid_string_filter_value';
    case InvalidEnumFilterValue = 'invalid_enum_filter_value';
    case InvalidDateFilterValue = 'invalid_date_filter_value';
    case InvalidDateTimeFilterValue = 'invalid_date_time_filter_value';
    case UnknownFilterAlias = 'unknown_filter_alias';
    case UnknownSortAlias = 'unknown_sort_alias';
    case UnknownTieBreakerSort = 'unknown_tie_breaker_sort';
    case InvalidMaximumFilters = 'invalid_maximum_filters';
    case InvalidMaximumSorts = 'invalid_maximum_sorts';
    case InvalidMaximumFilterValues = 'invalid_maximum_filter_values';
    case InvalidMaximumStringLength = 'invalid_maximum_string_length';
    case UnknownDefaultSort = 'unknown_default_sort';
    case InvalidFilterSchema = 'invalid_filter_schema';
    case InvalidDefaultSort = 'invalid_default_sort';
    case InvalidSortAlias = 'invalid_sort_alias';
    case InvalidSortColumn = 'invalid_sort_column';
    case InvalidFilterAlias = 'invalid_filter_alias';
    case InvalidFilterColumn = 'invalid_filter_column';
    case InvalidFilterOperators = 'invalid_filter_operators';
    case InvalidEnumValues = 'invalid_enum_values';
    case InvalidFilterShape = 'invalid_filter_shape';
    case UnsupportedFilterOperator = 'unsupported_filter_operator';
    case InvalidSortShape = 'invalid_sort_shape';
    case InvalidSortDirection = 'invalid_sort_direction';
    case DuplicateFilterOperator = 'duplicate_filter_operator';
    case MissingEnumValues = 'missing_enum_values';
    case UnexpectedEnumValues = 'unexpected_enum_values';
    case DuplicateEnumValue = 'duplicate_enum_value';
    case IncompatibleFilterOperator = 'incompatible_filter_operator';
    case NonNullableFilterOperator = 'non_nullable_filter_operator';
    case FilterComplexityExceeded = 'filter_complexity_exceeded';
    case DisallowedFilterOperator = 'disallowed_filter_operator';
    case UnexpectedFilterValue = 'unexpected_filter_value';
    case MissingFilterValue = 'missing_filter_value';
    case SortComplexityExceeded = 'sort_complexity_exceeded';
    case DuplicateSort = 'duplicate_sort';
    case UnsafeWrappedFilterColumn = 'unsafe_wrapped_filter_column';
    case DuplicateDefaultSort = 'duplicate_default_sort';
    case DuplicateFilterAlias = 'duplicate_filter_alias';
    case DuplicateSortAlias = 'duplicate_sort_alias';
    case FilterValueComplexityExceeded = 'filter_value_complexity_exceeded';
    case FilterStringTooLong = 'filter_string_too_long';
    case EmptyStringFilterValue = 'empty_string_filter_value';
}
