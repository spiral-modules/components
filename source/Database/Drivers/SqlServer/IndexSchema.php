<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\SqlServer;

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
        foreach ($schema as $index)
        {
            $this->type = $index['isUnique'] ? self::UNIQUE : self::NORMAL;
            $this->columns[] = $index['columnName'];
        }
    }
}