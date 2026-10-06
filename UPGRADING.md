# Upgrading NVL Filterable

## Consumer contracts, committed events and runtime policy (5.x)

Prefer focused public interfaces in constructor injection; native implementations remain container defaults and host prebindings win. Returned models are documented identity/data handles: use package contracts for reads/writes and capability-specific batch readers instead of direct package queries. Enable the shipped Core PHPStan include in your host; do not invoke the suite workbench static audit command in a consumer.

Events now carry immutable schemaVersion=1 and scalar/DTO snapshots. Replace model-bearing event fields with the IDs listed in [events](docs/events.md); load only through an authorized public reader when needed. Only six declared legacy `*Event` names are retained as PHP aliases for major 5, removal no earlier than major 6. Migrate exact imports/listeners/fakes to canonical names, replace suffix wildcard patterns explicitly, drain old queued payloads, rebuild event caches and restart workers. Framework Verified/PasswordReset remain native classes. Source-connection callbacks are process-local after-commit publication, not a durable outbox or exactly-once delivery.

Package failures have a marker and optional response metadata. Opt into Core's JSON renderer deliberately; preserve existing host handlers and request-locale selection. Missing required host adapters produce `binding_required`/500; genuine configured authorization denial retains native handling. See the README error table and required-bindings section where applicable.

Factories ship in runtime package mappings for host tests. Ordinary make may persist parents; withoutParents()->make creates detached fixtures. Supply persisted native owners/parents and active tenants explicitly, retain source revisions, and never treat a factory row as a real storage/provider/workflow effect. Core's optional installer publishes common config without enabling features; strict Doctor and explicit deployment cache/worker steps belong in the host release process. C3/C4/E executable acceptance is pending until recorded by integration.


## Upgrading to 1.0

Version 1.0 no longer reads the Request facade from a model trait and never accepts raw user columns or relations.

1. Declare filter definitions and sort aliases on the filtered model or query boundary.
2. Convert HTTP input with `QueryFilterSetFactory`.
3. Pass the resulting `FilterSet` to `EloquentFilterApplier` or the `Filterable` trait.
4. Replace unknown-column pass-through with explicit aliases.
5. Add handlers for any application-specific relation or database behavior.

Unsupported operators, malformed values, and complexity overflow now fail closed.

The finalized 1.0 contract includes these changes from early previews:

- Replace `FilterOperator::Not` / `not` with the unambiguous `NotEquals` / `not_equals` or `NotContains` / `not_contains`.
- Pass `SortDirection::Asc` or `SortDirection::Desc` to programmatic `SortCriterion` instances.
- Remove the third `SortDefinition` constructor argument. Express default direction with `defaultSorts: ['-created']`.
- Remove custom filter casters. Use a declared `FilterValueType`; custom handlers now receive normalized criteria.
- Use `fromHttpQuery()` in controllers to return safe 422 validation errors. Keep `fromQuery()` for transport-neutral callers that handle `FilterableException`.
- Explicit HTTP filter objects must contain only `operator` and the required `value`. Null-check operators must omit `value`.
- Add `maximumSorts`, `maximumValuesPerFilter`, and `maximumStringLength` where endpoint-specific limits differ from the defaults.
- Declare a sort alias and `tieBreakerSort` for deterministic pagination.

Boolean, integer, decimal, date, date-time, list, range, and empty-string parsing is now strict. Review clients that sent values such as `off`, scientific decimals, relative dates, rollover dates, timezone-less timestamps, empty strings, or dummy null-check values.

## Custom handlers are predicate-only

Custom handlers now run inside a nested `where` group. This keeps a handler's ordinary `orWhere` predicates inside the filter alias and preserves constraints already applied by the caller.

Review every custom handler before upgrading. Move joins, selected columns, grouping, and other query setup to the caller before applying the `FilterSet`, and express ordering with declared `SortDefinition` aliases. Handler return values remain compatible, but the handler must mutate only the nested predicate builder it receives.

Handlers are trusted application code, not an authorization sandbox. Raw SQL or other arbitrary callback behavior can still bypass application authorization, so authorize independently and keep handlers bounded and parameterized.

## Tagged consumer PHP boundary

Use source `@api` workflows, extension contracts, and value types for application integration. Direct use of untagged implementations or `@internal` members is unsupported. This classification keeps existing concrete Action signatures and runtime behavior; it does not authorize package model persistence, ad hoc queries, relation traversal, or generic model serialization. Returned models are identity/result handles with only the explicitly declared in-memory read fields described in the README.

## Application workflow contracts

The existing model scope now resolves EloquentFilterApplierContract. Its singleton default resolves the native concrete through the supplied container; parsing/schema APIs remain direct.

The supported workflow injection names are `EloquentFilterApplierContract`.

Inject these contracts when application workflows need substitution. Native
concrete constructors and operation signatures remain available through major 5;
internal workflow chains are unchanged. Register host implementations before
package discovery or replace the contract before resolving a new host service.
See [Testing your app](README.md#testing-your-app) for native fixtures and the
shipped consumer-audit PHPStan configuration.
