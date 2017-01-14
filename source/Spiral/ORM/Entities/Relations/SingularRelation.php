<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\Database\Exceptions\QueryException;
use Spiral\ORM\Exceptions\RelationException;
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

    /**
     * @param $value
     */
    protected function assertValid($value)
    {
        if (is_null($value)) {
            if (!$this->schema[Record::NULLABLE]) {
                throw new RelationException("Relation is not nullable");
            }
        } elseif (!is_a($value, $this->class, false)) {
            throw new RelationException(
                "Must be an instance of '{$this->class}', '" . get_class($value) . "' given"
            );
        }
    }
}