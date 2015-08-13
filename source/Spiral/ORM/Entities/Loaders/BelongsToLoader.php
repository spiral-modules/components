<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\ORM\Model;
use Spiral\ORM\Selector;

/**
 * Responsible for loading data related to parent model in belongs to relation. Loading logic is
 * identical to HasOneLoader however preferred loading methods is POSTLOAD.
 */
class BelongsToLoader extends HasOneLoader
{
    /**
     * Relation type is required to correctly resolve foreign model class based on relation
     * definition.
     */
    const RELATION_TYPE = Model::BELONGS_TO;

    /**
     * Default load method (inload or postload).
     */
    const LOAD_METHOD = self::POSTLOAD;
}