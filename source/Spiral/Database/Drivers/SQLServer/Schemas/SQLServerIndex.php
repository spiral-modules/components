<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Drivers\SQLServer\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractIndex;

class SQLServerIndex extends AbstractIndex
{
    /**
     * @param string $table Table name.
     * @param array  $schema
     *
     * @return SQLServerIndex
     */
    public static function createInstance(string $table, array $schema): self
    {
        //Schema is basically array of index columns merged with index meta
        $index = new self($table, current($schema)['indexName']);

        foreach ($schema as $index) {
            $index->type = $index['isUnique'] ? self::UNIQUE : self::NORMAL;
            $index->columns[] = $index['columnName'];
        }

        return $index;
    }
}