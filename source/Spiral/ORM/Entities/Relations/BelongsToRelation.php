<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\Database\Exceptions\QueryException;
use Spiral\ORM\Exceptions\SelectorException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;

class BelongsToRelation extends AbstractRelation
{
    /**
     * @var RecordInterface
     */
    private $instance;

    /**
     * {@inheritdoc}
     *
     * Returns associated parent or NULL if none associated.
     */
    public function getRelated()
    {
        if ($this->instance instanceof RecordInterface) {
            return $this->instance;
        }

        if (!$this->isLoaded()) {
            //Lazy loading our relation data
            $this->loadData();
        }

        if (empty($this->data)) {
            //No parent were defined
            return null;
        }

        //Create instance based on loaded data
        return $this->instance = $this->orm->make(
            $this->class,
            $this->data,
            ORMInterface::STATE_LOADED,
            true
        );
    }

    /**
     * Load related data thought ORM selectors.
     *
     * @throws SelectorException
     * @throws QueryException
     */
    protected function loadData()
    {
        $innerKey = $this->schema[Record::INNER_KEY];
        if (empty($this->parent->getField($innerKey))) {
            //Unable to load
            $this->loaded = true;
        }
    }
}