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
     * Lower-cased type names keyed by connection, table and column; null when unknown.
     *
     * @var array<string, string|null>
     */
    private array $types = [];

    /**
     * Resolve the lower-cased type name of a column, or null when it cannot be determined.
     */
    public function typeName(Connection $connection, string $table, string $column): ?string
    {
        $key = $connection->getName()."\0".$table."\0".$column;
        if (array_key_exists($key, $this->types)) {
            return $this->types[$key];
        }

        $type = null;
        foreach ($connection->getSchemaBuilder()->getColumns($table) as $definition) {
            if (strtolower($definition['name']) === strtolower($column)) {
                $type = strtolower($definition['type_name']);

                break;
            }
        }

        if (count($this->types) >= self::MAXIMUM_CACHED_COLUMNS) {
            array_shift($this->types);
        }

        return $this->types[$key] = $type;
    }
}
