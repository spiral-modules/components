<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\MySQL\Schemas;

use Spiral\Database\Schemas\AbstractIndex;

/**
 * MySQL index schema.
 */
class IndexSchema extends AbstractIndex
{
    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        foreach ($schema as $index) {
            $this->type = $index['Non_unique'] ? self::NORMAL : self::UNIQUE;
            $this->columns[] = $index['Column_name'];
        }
    }
}
