<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders\Traits;

use Spiral\Database\Builders\SelectQuery;
use Spiral\ORM\Entities\Loaders\AbstractLoader;
use Spiral\ORM\Record;

trait MorphedTrait
{
//    protected function configureMorphed(SelectQuery $query, AbstractLoader $parent)
//    {
//        //Need role somewhere here
//    }
//
//    /**
//     * Indication that relation is morphed.
//     *
//     * @return bool
//     */
//    protected function isMorphed(): bool
//    {
//        return !empty($this->localKey(Record::MORPH_KEY));
//    }
//
//    /**
//     * Generate sql identifier using loader alias and value from relation definition. Key name to be
//     * fetched from schema.
//     *
//     * Example:
//     * $this->getKey(Record::OUTER_KEY);
//     *
//     * @param string $key
//     *
//     * @return string|null
//     */
//    abstract protected function localKey($key);
}