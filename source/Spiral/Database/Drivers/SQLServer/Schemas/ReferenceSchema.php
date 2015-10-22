<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\SQLServer\Schemas;

use Spiral\Database\Entities\Schemas\AbstractReference;

/**
 * SQLServer foreign key schema.
 */
class ReferenceSchema extends AbstractReference
{
    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        $this->column = $schema['FKCOLUMN_NAME'];
        $this->foreignTable = $schema['PKTABLE_NAME'];
        $this->foreignKey = $schema['PKCOLUMN_NAME'];

        $this->deleteRule = $schema['DELETE_RULE'] ? self::NO_ACTION : self::CASCADE;
        $this->updateRule = $schema['UPDATE_RULE'] ? self::NO_ACTION : self::CASCADE;
    }
}