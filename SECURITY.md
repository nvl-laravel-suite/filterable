# Security Policy

Submit reports through [this package's private vulnerability reporting form](https://github.com/nvl-laravel-suite/filterable/security/advisories/new).

Security fixes are provided for the published `5.x` release line. Composer declares PHP `^8.4` and Laravel `^12.0|^13.0`. The local Dagger release gate verifies PHP 8.4/Laravel 13 with MySQL 8.4 and PostgreSQL 17 persistence contracts; PHP 8.5, Laravel 12 and MariaDB require separate compatibility evidence. Upstream security lifecycle limits still apply.

Report vulnerabilities privately through the repository host's security-advisory feature. Include the affected version, database driver, filter definition, input, generated query behavior, and impact.

Never register raw request column names, relation paths, SQL fragments, or unbounded custom handlers. Treat filter definitions as part of the application's authorization and data-exposure boundary.

Use `fromHttpQuery()` at HTTP boundaries, set endpoint-appropriate filter/sort/value/string limits, and declare a stable tie-breaker for paginated queries. Custom handlers receive normalized values but remain responsible for parameterized SQL and bounded query cost.
