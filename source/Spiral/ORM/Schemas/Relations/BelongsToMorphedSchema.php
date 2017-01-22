<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Relations\Traits\TablesTrait;
use Spiral\ORM\Schemas\SchemaBuilder;

/**
 * Provides ability to link record to mutable parent using interface definition.
 */
class BelongsToMorphedSchema extends AbstractSchema
{
    use TablesTrait;

    /**
     * Relation type.
     */
    const RELATION_TYPE = Record::BELONGS_TO_MORPHED;

    /**
     * {@inheritdoc}
     */
    public function declareTables(SchemaBuilder $builder): array
    {
       // $sourceTable = $this->sourceTable($builder);

        //echo 1;

        return [];

        return [$sourceTable];
    }
}