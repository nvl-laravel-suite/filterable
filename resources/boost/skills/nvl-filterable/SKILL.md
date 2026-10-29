---
name: nvl-filterable
description: Implement, integrate, test, or review native nvl/filterable in Laravel 13. Use for allowlisted Eloquent filters, scalar and list normalization, safe relation predicates, sort aliases and query complexity.
---

# NVL Filterable

Use `FilterSchema`, `FilterDefinition` and `SortDefinition` to allowlist every alias, database path, type, operator, enum value and null behavior. Declare bounded filter/sort/value/string limits, defaults and a stable tie-breaker. Never accept caller-provided columns, relations or SQL fragments.

Call `EloquentFilterApplierContract::apply($query, $schema, $filters = [], $sorts = [])`, or a host model's `applyFilters($filters, $sorts)` scope. Both arrays are lists:

- Filters: `array{alias:string,operator:FilterOperator|string,value?:scalar|array|null}`. Sets/ranges require native scalar lists. Other values must match the declared type. Null checks allow omission or explicit null.
- Sorts: `array{alias:string,direction:'asc'|'desc'}`. Exact lowercase direction and declared aliases are required.

The applier validates all entries before changing the builder. Unknown keys, unsupported aliases/operators, malformed values, duplicates and excess complexity fail closed. Empty sorts select schema defaults and append the tie-breaker once. Contains values are literal substrings with escaped wildcards.

Numeric strings, date-time instants, aliases and database paths reject trailing newlines. Date-time offsets must have valid hours and minutes before UTC normalization; PHP's permissive offset parser is not the input contract.

Consumers own HTTP parsing, comma splitting, scalar shorthand, DTOs, frontend types and response mapping. Package Data carriers and the HTTP factory are retired without aliases. Catch `FilterableException` in a controller and use `toValidationException()` where a 422 response is desired. Direct CLI/job calls receive the same validation guarantees.

Custom handlers receive a nested Eloquent builder and normalized `array{alias:string,operator:FilterOperator,value:mixed}`; date-times normalize to UTC CarbonImmutable. Handlers mutate predicates only and execute inside grouped where clauses, preserving caller predicates when ordinary orWhere is used. They are trusted callbacks, so authorize independently and keep SQL parameterized.

Inject the focused applier contract for host substitution. Native container prebindings remain intact. Use real host-owned rows to test filtering correctness; a mock proves orchestration only. Test malicious shapes/keys, aliases, strict types, null/empty values, lists/ranges, dates/zones, literals, relations, custom OR predicates, limits, duplicate sorts and deterministic pagination. Verify production driver behavior explicitly.

The only family dependency is Core's native exception/resource support. Data, transformer and Ownership runtimes are not required. No config or migration is shipped. Publish guidance through `nvl-filterable-skills` and translations through `nvl-filterable-translations`; preserve host resource names. Consult README.md and UPGRADING.md for native examples.
