<?php

declare(strict_types=1);

namespace Nvl\Filterable\Services;

use Illuminate\Database\Connection;

/**
 * Looks up and caches database column type names for date-time bindings.
 *
 * @internal
 */
final class ColumnStorageTypes
{
    /**
     * Maximum number of cached columns kept per process.
     */
    private const int MAXIMUM_CACHED_COLUMNS = 256;

    /**
     * Lower-cased type names keyed by connection, configured PostgreSQL search path, prefixed table
     * (schema-qualified when given) and column; null when unknown.
     *
     * @var array<string, string|null>
     */
    private array $types = [];

    /**
     * Resolve the lower-cased type name of a column, or null when it cannot be determined.
     *
     * The column name matches exactly, as PostgreSQL treats quoted identifiers that differ only in
     * case as distinct columns. PostgreSQL resolves an unqualified table through the session search
     * path exactly as the filtered query does, not only in the first schema of that path.
     */
    public function typeName(Connection $connection, string $table, string $column): ?string
    {
        $searchPath = $connection->getConfig('search_path') ?? $connection->getConfig('schema');
        $key = $connection->getName()."\0".json_encode($searchPath)."\0".$connection->getTablePrefix()."\0".$table."\0".$column;
        if (array_key_exists($key, $this->types)) {
            return $this->types[$key];
        }

        $type = $connection->getDriverName() === 'pgsql'
            ? $this->postgresTypeName($connection, $table, $column)
            : $this->schemaTypeName($connection, $table, $column);

        if (count($this->types) >= self::MAXIMUM_CACHED_COLUMNS) {
            array_shift($this->types);
        }

        return $this->types[$key] = $type;
    }

    /**
     * Read the type of the relation the query itself resolves, honoring the session search path.
     */
    private function postgresTypeName(Connection $connection, string $table, string $column): ?string
    {
        $row = $connection->selectOne(
            'select t.typname as type_name from pg_attribute a join pg_type t on t.oid = a.atttypid '
            .'where a.attrelid = to_regclass(?) and a.attname = ? and a.attnum > 0 and not a.attisdropped',
            [$connection->getQueryGrammar()->wrapTable($table), $column],
        );
        $type = is_object($row) ? ($row->type_name ?? null) : null;

        return is_string($type) ? strtolower($type) : null;
    }

    private function schemaTypeName(Connection $connection, string $table, string $column): ?string
    {
        foreach ($connection->getSchemaBuilder()->getColumns($table) as $definition) {
            if ($definition['name'] === $column) {
                return strtolower($definition['type_name']);
            }
        }

        return null;
    }
}
