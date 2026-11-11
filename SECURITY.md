# Security Policy

Submit reports through [this package's private vulnerability reporting form](https://github.com/nvl-laravel-suite/filterable/security/advisories/new).

Security fixes are provided for the published `7.x` release line. Earlier lines no longer receive fixes. NVL Light declares PHP `^8.4` and Laravel `^13.0`. Package-local tests and an isolated production install do not establish release certification or cross-database compatibility. Upstream security lifecycle limits still apply.

Report vulnerabilities privately through the repository host's security-advisory feature. Include the affected version, database driver, filter definition, input, generated query behavior, and impact.

Never register raw request column names, relation paths, SQL fragments, or unbounded custom handlers. Treat filter definitions as part of the application's authorization and data-exposure boundary.

Validate and map HTTP input to native filter and sort lists in the consumer, set endpoint-appropriate filter/sort/value/string limits, and declare a stable tie-breaker for paginated queries. Custom handlers receive normalized values but remain responsible for parameterized SQL and bounded query cost.
