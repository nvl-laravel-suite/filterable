# Changelog


All notable changes to `nvl/filterable` are documented here.

## [Unreleased]

### Fixed

- `maximumStringLength` now counts characters instead of bytes, so multibyte values such as Cyrillic text are no longer rejected at half the configured limit.
- String filter values that are not valid UTF-8 or contain a NUL byte, and dates or date-times outside years 0001–9999, are rejected as invalid filter values instead of reaching the database, where PostgreSQL answered them with a query error.
- `FilterDefinition` accepts optional `minimum`/`maximum` bounds for integer filters, so a value outside a 32-bit column's range is rejected as `invalid_integer_filter_value` instead of failing the PostgreSQL query.
- Native sorts accept a `SortDirection` case as well as `asc`/`desc`; the normalizer now validates directions through the enum.
- A normalized criterion or sort without a schema definition now fails loudly instead of being skipped silently.

## [7.0.1] - 2026-11-05

### Fixed

- Preserve fractional date-time precision in native scalar, set and range query bindings while retaining caller predicates and normalized Carbon values for custom handlers.

<!-- NVL GENERATED CHANGES START -->
### Changed

- preserve fractional date-time bindings
<!-- NVL GENERATED CHANGES END -->

## [7.0.0] - 2026-11-03

- Adopt the reviewed NVL 7 family for PHP 8.4+ and Laravel 13, with compatible required and optional NVL peers. Review the package upgrade guide before deployment.

### Changed

- Align NVL peers and consumer contracts on the coordinated 7.x line. Review [UPGRADING.md](UPGRADING.md) for adoption and application-owned integration requirements.

## [6.0.0] - 2026-10-20

### Changed

- Keep filtering independent of the enforcement runtime and preserve mandatory canonical owner predicates when composing filters. Do not allow caller OR conditions or relation filters to widen isolation.
- Align required/development NVL peers on `^6.0`, optional peer conflicts on major 6, and `dev-main` on `6.x-dev`. Review [UPGRADING.md](UPGRADING.md) before rollout.

## [5.0.1] - 2026-10-08

### Changed

- Correct published-family verification and adoption guidance for local Dagger CI; runtime contracts are unchanged.


## [5.0.0] - 2026-10-08

### Changed

- The existing model scope now resolves EloquentFilterApplierContract. Its singleton default resolves the native concrete through the supplied container; parsing/schema APIs remain direct. Document contract substitution and truthful host fixtures in Testing your app.
- Classify the supported consumer PHP surface with explicit source annotations and restrict package model handles to declared identity and in-memory read fields; preserve existing workflow behavior and concrete signatures.
- Adopt lockstep major 5 with required and development NVL peer floors of `^5.0`.
- Keep filtering behavior unchanged; adopt canonical configuration and the lockstep major.
- Review [UPGRADING.md](UPGRADING.md) before adopting the new names and infrastructure boundaries.

## [2.2.1] - 2026-09-26

### Documentation

- Clarify public support, contribution, and private security reporting paths.

## [2.2.0] - 2026-09-25

### Changed

- Prepare `nvl/filterable` for independent Composer and Git publication; require `nvl/core` for shared Support and Data services.

## [2.0.1] - 2026-09-22

### Fixed

- Group custom handler predicates so ordinary `orWhere` clauses preserve
  constraints already applied by the caller.

## [2.0.0] - 2026-08-29

### Changed

- Declared Laravel 13 and `nvl/data` 2.x compatibility while preserving typed
  `FilterSet` contracts as the explicit Suite 2.0 consumer-query boundary.

## [1.0.7] - 2026-08-22

### Changed

- Aligned the documented runtime requirement with the PHP 8.4+ package
  baseline.

## [1.0.5] - 2026-08-12

### Changed

- Corrected the historical v1.0.0 release date and classified its already
  shipped filtering work under that stable release.

## [1.0.0] - 2026-08-08

- Added typed, pure `FilterSet` contracts and an isolated HTTP adapter.
- Added allowlisted filter and sort definitions with bounded relation complexity.
- Added portable scalar, set, range, null, date, and date-time semantics.
- Removed implicit request parsing and faceted-search claims.
- Replaced ambiguous `not` semantics with `not_equals` and `not_contains`.
- Added strict query-object parsing, strict scalar/date normalization, and literal wildcard escaping.
- Added sort, set-value, and string-length complexity limits plus duplicate-sort rejection.
- Added stable exception codes and paths with an HTTP-safe 422 adapter.
- Added enum-backed sort directions, deterministic tie-breaker sorting, and indexed schema lookups.
- Added schema-time identifier, operator/type, nullability, and enum validation.
- Split value normalization from Eloquent predicate application and normalized custom-handler criteria.
- Added generated TypeScript value unions and sort directions.
- Added SQLite, PostgreSQL, and MySQL package coverage in CI.
