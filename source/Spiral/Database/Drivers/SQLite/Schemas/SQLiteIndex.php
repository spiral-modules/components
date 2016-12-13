<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\SQLite\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractIndex;

class SQLiteIndex extends AbstractIndex
{
    /**
     * @param string $table
     * @param array  $schema
     * @param array  $columns
     *
     * @return SQLiteIndex
     */
    public static function createInstance(string $table, array $schema, array $columns): self
    {
        $index = new self($table, $schema['name']);
        $index->type = $schema['unique'] ? self::UNIQUE : self::NORMAL;

        foreach ($columns as $column) {
            $index->columns[] = $column['name'];
        }

        return $index;
    }
}
