---
name: nvl-filterable
description: Implement, integrate, test, or review nvl/filterable in Laravel 13. Use for allowlisted Eloquent filters, typed filter sets, HTTP query parsing, safe relation filtering, sort aliases, operator rules, complexity limits, or database-portable filtering.
---

# NVL Filterable

Treat filtering as an explicit allowlisted query contract, not a general search or facets engine.

## Declare filters

- Define every accepted alias, target column or relation, compatible operator set, value type, null behavior, and custom handler.
- Declare sort aliases, deterministic defaults, and a stable pagination tie-breaker.
- Never pass user-provided column names, relation paths, or directions directly to Eloquent.
- Keep relation aliases shallow and bounded.

## Parse and apply

- Build a typed `FilterSet` independently of HTTP.
- Use `QueryFilterSetFactory::fromHttpQuery()` at request boundaries and `fromQuery()` for transport-neutral callers.
- Apply criteria through `EloquentFilterApplier` or the `Filterable` trait.
- Reject malformed query objects, booleans, ranges, sets, dates, arrays, duplicate sorts, unsupported operators, and excess filter/sort/value/string complexity.
- Treat `contains` and `not_contains` values as literal substrings by escaping SQL wildcard characters.
- Expect custom handlers to receive already-normalized criteria.
- Keep custom handlers predicate-only. They run inside a nested `where` group so ordinary `orWhere` clauses preserve caller predicates.
- Prepare joins and selected columns before applying filters, and express ordering through declared sort definitions.
- Treat handlers as trusted callbacks rather than an authorization sandbox; raw SQL can still bypass application rules, so authorize independently.
- Choose an explicit driver strategy where string or date behavior differs by database.

## Verify

Test injection payloads, unknown aliases, null and empty values, booleans, sets, ranges, date-time zones, relations, custom handlers, default sorting, complexity limits, and SQLite/PostgreSQL/MySQL parity.

## Configurable-tenancy release discipline

- Preserve disabled compatibility and package independence; tenant support never creates an undeclared Auth or Suite dependency.
- Use registered package-owned resources, adoption adapters, Actions, and lifecycle APIs. Never add a generic tenant delete-all path or raw cross-package cleanup.
- Treat mapping/configuration hashes, interruption checkpoints, conservation evidence, worker context, tenant-leading queries, and standalone consumption as release contracts.

## Canonical configuration ownership

- This package does not ship a package config file or package environment variables. Configure behavior through its typed APIs; do not invent a `nvl-filterable` config root.
- When composing configured NVL packages, use their shipped canonical `nvl-<package>` roots and `NVL_<PACKAGE>_*` inputs. Core's generic config/environment compatibility is default off and applies only to explicitly selected historical inputs.
- Keep logical package/tenant resource identifiers unchanged and use the package's canonical skill publication tag. Preserve host global registrations.

## Application workflow substitution

The existing model scope now resolves EloquentFilterApplierContract. Its singleton default resolves the native concrete through the supplied container; parsing/schema APIs remain direct.

The supported workflow injection names are `EloquentFilterApplierContract`.

Inject the supported contract into host orchestration and bind a native interface
mock or host implementation before resolving that orchestration. Keep concrete
constructors and native workflow bodies intact; internal chains remain package-owned.
Use declared DTOs or unsaved model identity handles for orchestration fixtures.
Use real package workflows and Laravel framework fakes for persistence, tenant,
queue, file, and external-effect integration checks. A host substitute proves
only the host call and result. Keep public declarations tagged `@api` and
constructor/configuration/private helpers internal.

Consult the owning README's Testing your app section for native examples. Include
`vendor/nvl/core/support/consumer-audit.neon` in host PHPStan and declare explicit
`nvlConsumer.testPaths`; the Suite workbench is not consumer tooling.


## Consumer runtime and testing contracts

Start with the package README Quickstart and Testing your app sections. Use `nvl:install <package>` for loaded-package common config publication; it does not enable features, run schema or refresh caches. Preserve native host owner keys/morph maps and selected auth/tenancy defaults. Read full runtime defaults and publish advanced config only deliberately.

Inject the supported focused interfaces and preserve host bindings. Returned model handles do not permit package-table queries/writes outside documented capability/extension seams. Host tests may substitute contracts in Laravel's container, use shipped model factories (ordinary make may persist parents; withoutParents()->make is detached), and use Laravel effect fakes deliberately. Only Media/Stripe have dedicated provider/library fakes; do not invent a universal package fake. Settings InteractsWithSettings is definition-only. Host PHPStan may include vendor/nvl/core/support/consumer-audit.neon; no unpublished workbench command is a consumer requirement.

Read docs/events.md and the package README error table. Domain events use schemaVersion=1, model-free facts and actual source-connection commit callbacks; only six declared old Event suffix aliases remain for major 5. Migrate exact listeners/fakes and suffix wildcards, drain old queued payloads, rebuild event cache and restart workers. Delivery is not a durable outbox. The Core exception renderer is opt-in, JSON-only for respondable failures, with exactly message/code/context and host-selected locale. Do not expose diagnostics or reinterpret missing bindings as authorization denial.

Core package logging uses nvl/normal with CSV quiet by default, stable message keys and bounded context; incidents survive quiet. Do not mutate global logger context or log raw row/provider/content/credential payloads. Run only authorized project checks and report new acceptance as pending until actual output exists.
