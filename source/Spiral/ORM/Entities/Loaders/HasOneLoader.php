<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

/**
 * Dedicated to load HAS_ONE relations, by default loader will prefer to join data into query.
 * Loader support MORPH_KEY.
 */
class HasOneLoader extends RelationLoader
{

}