<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Model;

class HasManySchema extends HasOneSchema
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = Model::HAS_MANY;

    /**
     * Default definition parameters, will be filled if parameter skipped from definition by user.
     * Has many relation allows us user custom condition and prefill filed.
     *
     * @invisible
     * @var array
     */
    protected $defaultDefinition = [
        Model::INNER_KEY         => '{record:primaryKey}',
        Model::OUTER_KEY         => '{record:roleName}_{definition:INNER_KEY}',
        Model::CONSTRAINT        => true,
        Model::CONSTRAINT_ACTION => 'CASCADE',
        Model::NULLABLE          => true,
        Model::WHERE             => []
    ];
}