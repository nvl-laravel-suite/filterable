---
name: nvl-filterable
description: Use when adding allowlisted Eloquent filtering, relation predicates, typed query normalization, bounded sorting or model filter schemas in Laravel.
---

# NVL Filterable

Filterable applies a declared query schema to an existing Eloquent builder.
The host owns authorization, ownership predicates, HTTP parsing and pagination.
It exposes no HTTP endpoint, database model, migration or configuration file.

## References

- [Installation](references/installation.md): requirements, discovery and actual publication tags.
- [API](references/api.md): definitions, enums, applier signatures and error contracts.
- [Usage](references/usage.md): native schema, model scope, relation/custom predicates and verification.

Read usage before composing an endpoint and API before selecting operators or limits.

## Choose the integration

Inject `EloquentFilterApplierContract` to apply filters to an already scoped query.
Use the `Filterable` model trait when a host model should expose `applyFilters`.
Implement that model's `filterSchema()` using immutable definitions.
The focused applier contract supports an application substitute; provider registration preserves a host prebinding.
No model substitution registry is needed: the host owns the queried model.

## Declare the allowlist

Use `FilterSchema`, `FilterDefinition` and `SortDefinition`.
Declare every public alias, database column/relation path, type and operator.
Only enum definitions accept enum values; declare their complete allowed list.
Null operators require `nullable: true`.
Sort definitions use native columns; do not accept caller-provided SQL or relation paths.
Configure defaults and one declared stable tie-breaker for deterministic pages.
Choose complexity bounds appropriate to the endpoint before receiving input.

## Native input

Filters and sorts are lists, including when only one entry is supplied.
A filter is `array{alias:string,operator:FilterOperator|string,value?:mixed}`.
A sort is `array{alias:string,direction:'asc'|'desc'}`.
All keys are exact; HTTP scalar shorthand, comma splitting and DTO mapping belong to the host.
Omitted or explicit null values are valid only for declared null checks, which require `nullable: true`.
`in`/`not_in` require scalar lists; `between` requires exactly two values.
Empty sorts select schema defaults and append the tie-breaker once.
Unknown keys, aliases, operators, malformed values and excessive complexity fail before builder mutation.

## Query guarantees

Preserve the caller's visibility and ownership predicates before applying filters.
Relation filters use declared paths; contains predicates escape wildcard characters as literal text.
Datetime input normalizes valid instants to UTC; permissive PHP parsing is not the contract.
Custom handlers receive a nested builder and normalized criterion.
Keep handler predicates grouped and SQL parameterized; trusted callbacks remain host code.
The service mutates/returns a builder and does not execute or authorize the final query.
It supplies no transaction, revision, idempotency or event workflow because it performs no writes.

## Bound and verify

Default maxima are 25 filters, 5 sorts, 100 values per filter and 255 string bytes.
Use a bounded paginator; Filterable's complexity limits do not bound the returned row count.
Test real rows for numeric/date/null semantics, literal wildcard text and relation membership.
Test malformed shapes and verify an invalid call leaves the builder unchanged.
Test custom OR handlers against a pre-existing caller predicate.
Verify default sorting and tie-breaking on the actual database driver.
Use `FilterableException`'s stable code/field context for host transport error mapping.
Core supplies native support; Data, TypeScript and Ownership packages are not runtime requirements.
No optional library feature needs activation and no package maintenance command is registered.
Run the affected application tests with `php artisan test --compact`.
