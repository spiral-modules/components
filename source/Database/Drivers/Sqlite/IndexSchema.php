<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\Sqlite;

use Spiral\Database\Schemas\AbstractIndex;

class IndexSchema extends AbstractIndex
{
    /**
     * Parse index information provided by parent TableSchema and populate index values.
     *
     * @param mixed $schema Index information fetched from database by TableSchema. Format depends
     *                      on driver type.
     * @return mixed
     */
    protected function resolveSchema($schema)
    {
        $this->name = $schema['name'];
        $this->type = $schema['unique'] ? self::UNIQUE : self::NORMAL;

        $indexColumns = $this->table->getDriver()->query("PRAGMA INDEX_INFO({$this->getName(true)})");
        foreach ($indexColumns as $column)
        {
            $this->columns[] = $column['name'];
        }
    }
}