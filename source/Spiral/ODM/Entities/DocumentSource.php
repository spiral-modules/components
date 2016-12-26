<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Entities;

use Spiral\Core\Component;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\ODM\CompositableInterface;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Exceptions\SourceException;
use Spiral\ODM\ODM;

/**
 * Source class associated to one or multiple (default implementation) ODM models. Source can be
 * used to write your own custom find method or change default selection.
 */
class DocumentSource extends Component implements \Countable, \IteratorAggregate
{
    use SaturateTrait;

    /**
     * Linked document model. ODM can automatically index and link user sources to models based on
     * value of this constant.
     */
    const DOCUMENT = null;

    /**
     * @var DocumentSelector
     */
    private $selector;

    /**
     * Associated document class.
     *
     * @var string
     */
    private $class = null;

    /**
     * @invisible
     *
     * @var ODM
     */
    protected $odm = null;

    /**
     * @param string $class
     * @param ODM    $odm
     *
     * @throws SourceException
     */
    public function __construct(string $class = null, ODM $odm = null)
    {
        if (empty($class)) {
            if (empty(static::DOCUMENT)) {
                throw new SourceException('Unable to create source without associated class');
            }

            $class = static::DOCUMENT;
        }

        $this->class = $class;
        $this->odm = $this->saturate($odm, ODM::class);
    }

    /**
     * Create new DocumentEntity based on set of provided fields.
     *
     * @final Change static method of entity, not this one.
     *
     * @param array  $fields
     * @param string $class  Due ODM models can be inherited you can use this argument to specify
     *                       custom model class.
     *
     * @return CompositableInterface|DocumentEntity
     */
    public function create($fields = [], string $class = null)
    {
        //Create model with filtered set of fields
        return $this->odm->instantiate($class ?? $this->class, $fields, true);
    }

    /**
     * Find document by it's primary key.
     *
     * @see findOne()
     *
     * @param string|\MongoId $id Primary key value.
     *
     * @return CompositableInterface|DocumentEntity|null
     */
    public function findByPK($id)
    {
        return $this->findOne(['_id' => ODM::mongoID($id)]);
    }

    /**
     * Select one document from mongo collection.
     *
     * @param array $query  Fields and conditions to query by.
     * @param array $sortBy Always specify sort by to ensure that results are stable.
     *
     * @return CompositableInterface|DocumentEntity|null
     */
    public function findOne(array $query = [], array $sortBy = [])
    {
        return $this->getIterator()->sortBy($sortBy)->findOne($query);
    }

    /**
     * Get associated document selection with pre-configured query (if any).
     *
     * @param array $query
     *
     * @return DocumentSelector
     */
    public function find(array $query = []): DocumentSelector
    {
        return $this->getIterator()->where($query);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->getIterator()->count();
    }

    /**
     * @return DocumentSelector
     */
    public function getIterator(): DocumentSelector
    {
        if (empty($this->selector)) {
            //Requesting selector on demand
            $this->selector = $this->odm->selector($this->class);
        }

        return clone $this->selector;
    }


    /**
     * Create source with new associated selector.
     *
     * @param DocumentSelector $selector
     *
     * @return DocumentSource
     */
    public function withSelector(DocumentSelector $selector): DocumentSource
    {
        $source = clone $this;
        $source->setSelector($selector);

        return $source;
    }

    /**
     * Set initial selector.
     *
     * @param DocumentSelector $selector
     */
    protected function setSelector(DocumentSelector $selector)
    {
        $this->selector = clone $selector;
    }

    /**
     * {@inheritdoc}
     */
    protected function iocContainer()
    {
        if ($this->odm instanceof Component) {
            //Always work in ODM scope
            return $this->odm->iocContainer();
        }

        return parent::iocContainer();
    }
}