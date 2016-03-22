<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\MySQL\Schemas;

use Spiral\Database\Entities\Schemas\AbstractReference;

/**
 * MySQL foreign key schema.
 */
class ReferenceSchema extends AbstractReference
{
    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        $this->column = $schema['COLUMN_NAME'];

        $this->foreignTable = $schema['REFERENCED_TABLE_NAME'];
        $this->foreignKey = $schema['REFERENCED_COLUMN_NAME'];

        $this->deleteRule = $schema['DELETE_RULE'];
        $this->updateRule = $schema['UPDATE_RULE'];
    }
}
