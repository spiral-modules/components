<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

/**
 * Responsible for loading data related to parent record in belongs to relation. Loading logic is
 * identical to HasOneLoader however preferred loading methods is POSTLOAD.
 */
class BelongsToLoader extends RelationLoader
{

}