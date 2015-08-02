<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\SQLServer\Schemas;

use Spiral\Database\Entities\Schemas\AbstractIndex;

/**
 * SQLServer index schema.
 */
class IndexSchema extends AbstractIndex
{
    /**
     * {@inheritdoc}
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