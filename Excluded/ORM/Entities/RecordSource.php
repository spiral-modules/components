<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities;

use Spiral\Core\Component;
use Spiral\Core\Exceptions\SugarException;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\ORM\Exceptions\SourceException;
use Spiral\ORM\ORM;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\RecordEntity;

/**
 * Source class associated to one or multiple (default implementation) ORM models. Source can be
 * used to write your own custom find method or change default selection.
 */
class RecordSource extends Component implements \Countable
{
    use SaturateTrait;

    /**
     * Linked document model. ORM can automatically index and link user sources to models based on
     * value of this constant.
     */
    const RECORD = null;

    /**
     * Associated document class.
     *
     * @var string
     */
    private $class = null;

    /**
     * @var RecordSelector
     */
    private $selector = null;

    /**
     * @invisible
     *
     * @var ORM
     */
    protected $orm = null;

    /**
     * @param string       $class
     * @param ORMInterface $orm
     *
     * @throws SugarException
     */
    public function __construct($class = null, ORMInterface $orm = null)
    {
        if (empty($class)) {
            if (empty(static::RECORD)) {
                throw new SourceException('Unable to create source without associated class');
            }

            $class = static::RECORD;
        }

        $this->class = $class;
        $this->orm = $this->saturate($orm, ORMInterface::class);
        $this->setSelector($this->orm->selector($this->class));
    }

    /**
     * Create new Record based on set of provided fields.
     *
     * @final Change static method of entity, not this one.
     *
     * @param array $fields
     *
     * @return RecordEntity
     */
    final public function create($fields = [])
    {
        //Letting entity to create itself (needed
        return call_user_func([$this->class, 'create'], $fields, $this->orm);
    }

    /**
     * Find record by it's primary key.
     *
     * @see findOne()
     *
     * @param string|int $id Primary key value.
     *
     * @return RecordEntity|null
     */
    public function findByPK($id)
    {
        return $this->find()->findByPK($id);
    }

    /**
     * Select one record from mongo collection.
     *
     * @param array $where   Where conditions in array form.
     * @param array $orderBy In a form of [key => direction].
     *
     * @return RecordEntity|null
     */
    public function findOne(array $where = [], array $orderBy = [])
    {
        return $this->find()->orderBy($orderBy)->findOne($where);
    }

    /**
     * Get associated record selection with pre-configured query (if any).
     *
     * @param array $where Where conditions in array form.
     *
     * @return RecordSelector
     */
    public function find(array $where = [])
    {
        return $this->selector()->where($where);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->find()->count();
    }

    /**
     * @param RecordSelector $selector
     */
    protected function setSelector(RecordSelector $selector)
    {
        $this->selector = $selector;
    }

    /**
     * @return RecordSelector
     */
    final protected function selector()
    {
        //Has to be cloned every time to prevent query collisions
        return clone $this->selector;
    }

    /**
     * {@inheritdoc}
     */
    protected function iocContainer()
    {
        if ($this->orm instanceof Component) {
            return $this->orm->container();
        }

        return parent::iocContainer();
    }
}
