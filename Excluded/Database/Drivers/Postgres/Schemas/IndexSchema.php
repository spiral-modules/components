<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\Postgres\Schemas;

use Spiral\Database\Schemas\AbstractIndex;

/**
 * Postgres index schema.
 */
class IndexSchema extends AbstractIndex
{
    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        $this->type = strpos($schema, ' UNIQUE ') ? self::UNIQUE : self::NORMAL;

        if (preg_match('/\(([^)]+)\)/', $schema, $matches)) {
            $this->columns = explode(',', $matches[1]);

            foreach ($this->columns as &$column) {
                //Postgres with add quotes to all columns with uppercase letters
                $column = trim($column, ' "\'');
                unset($column);
            }
        }
    }
}
