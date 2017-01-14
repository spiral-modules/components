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

/**
 * Provides ability to create cached instances of related data.
 */
abstract class SingularRelation extends AbstractRelation
{
    /**
     * Create placeholder model when relation is empty.
     */
    const CREATE_PLACEHOLDER = false;

    /**
     * @var RecordInterface
     */
    protected $instance;

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
            if (static::CREATE_PLACEHOLDER) {
                //Stub instance
                return $this->instance = $this->orm->make(
                    $this->class,
                    [],
                    ORMInterface::STATE_NEW
                );
            }

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
     * Must load related data using appropriate method.
     */
    /**
     * {@inheritdoc}
     *
     * @throws SelectorException
     * @throws QueryException
     */
    protected function loadData()
    {
        $this->loaded = true;

        $innerKey = $this->schema[Record::INNER_KEY];
        if (empty($this->parent->getField($innerKey))) {
            //Unable to load
            return;
        }

        $this->data = $this->orm->selector($this->class)->where(
            $this->schema[Record::OUTER_KEY],
            $this->parent->getField($innerKey)
        )->fetchData();

        if (!empty($this->data[0])) {
            //Use first result
            $this->data = $this->data[0];
        }
    }
}