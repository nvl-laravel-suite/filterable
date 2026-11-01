# Install and publish: nvl/filterable

Targets PHP 8.4+ and Laravel 13. Install the selected NVL dependency closure on compatible 7.x versions.

```sh
composer require nvl/filterable:^7.0
```

## Provider discovery

Composer package discovery registers these package-owned providers:

- `Nvl\Filterable\Providers\FilterableServiceProvider`

Do not publish or copy vendor providers into the application. If discovery is deliberately disabled, register these exact classes in the application’s `bootstrap/providers.php`. Host policy and extension bindings belong in an application provider; see [usage](usage.md) for which bindings are required.

## Skills

Laravel Boost discovers this folder at `resources/boost/skills/` in the installed Composer package. In the consumer application, run:

```sh
php artisan boost:install --skills --no-interaction
```

Select skills through the installed Boost configuration when setting it up interactively. `php artisan boost:update --no-interaction` refreshes selected resources. Boost is a consumer development tool, not a required runtime dependency of this package. Agent Skills folders can also be copied to the skill directory supported by another agent; copy the entire folder so references and any scripts remain available.

The package also exposes its native Laravel publish group:

```sh
php artisan vendor:publish --tag=nvl-filterable-skills --no-interaction
```

That group copies the full skill tree to `.agents/skills`. Avoid force-overwriting application-authored changes; keep local conventions separate from vendor guidance.

## Publishable groups

- `nvl-filterable-skills`
- `nvl-filterable-translations`

This package ships no bundled configuration file or config publish group. Configure only host settings explicitly documented in [usage](usage.md); do not invent a config publication command.
Filterable has no configuration namespace; declare filters, sort aliases and limits through its native schema and service arguments.

## Migrations

This package publishes no database migrations. Host models or schemas required by a capability are application-owned; follow the declarations and constraints in [usage](usage.md).

## Artisan operations

This package declares no Artisan commands. Do not invent package install, doctor, schema or DTO-generation commands.

## Verify the consumer

Use the package-specific checks in [usage](usage.md), verify required host bindings and run the affected consumer tests. Publishing files is not application or production certification. Preserve the documented explicit actor, revision, owner, locale and outer-commit requirements when adapting HTTP, CLI or jobs.

Official framework guidance: [Laravel package development](https://laravel.com/docs/13.x/packages) and [Boost third-party package skills](https://laravel.com/docs/13.x/boost#third-party-package-skills).
