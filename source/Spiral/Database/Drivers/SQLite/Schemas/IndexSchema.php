<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\SQLite\Schemas;

use Spiral\Database\Entities\Schemas\AbstractIndex;

/**
 * SQLite index schema.
 */
class IndexSchema extends AbstractIndex
{
    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        $this->name = $schema['name'];
        $this->type = $schema['unique'] ? self::UNIQUE : self::NORMAL;

        $indexColumns = $this->table->driver()->query("PRAGMA INDEX_INFO({$this->getName(true)})");
        foreach ($indexColumns as $column) {
            $this->columns[] = $column['name'];
        }
    }
}