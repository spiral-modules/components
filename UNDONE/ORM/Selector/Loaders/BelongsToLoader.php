<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Selector\Loaders;

use Spiral\ORM\Model;
use Spiral\ORM\Selector;

class BelongsToLoader extends HasOneLoader
{
    /**
     * Relation type is required to correctly resolve foreign model.
     */
    const RELATION_TYPE = Model::BELONGS_TO;

    /**
     * Default load method (inload or postload).
     */
    const LOAD_METHOD = Selector::POSTLOAD;
}