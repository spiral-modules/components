<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

use Spiral\Core\Component;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\SourceInterface;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Exceptions\SourceException;
use Spiral\ODM\ODM;

/**
 * Source class associated to one or multiple (default implementation) ODM models. Source can be
 * used to write your own custom find method or change default selection.
 */
class DocumentSource extends Component implements SourceInterface, \Countable
{
    /**
     * Sugary!
     */
    use SaturateTrait;

    /**
     * Linked document model. ODM can automatically index and link user sources to models based on
     * value of this constant.
     */
    const DOCUMENT = null;

    /**
     * Associated document class.
     *
     * @var string
     */
    private $class = null;

    /**
     * @var DocumentSelector
     */
    private $selector = null;

    /**
     * @invisible
     * @var ODM
     */
    protected $odm = null;

    /**
     * @param string $class
     * @param ODM    $odm
     * @throws SourceException
     */
    public function __construct($class = null, ODM $odm = null)
    {
        if (empty($class)) {
            if (empty(static::DOCUMENT)) {
                throw new SourceException("Unable to create source without associate class.");
            }

            $class = static::DOCUMENT;
        }

        $this->class = $class;

        $this->odm = $this->saturate($odm, ODM::class);
        $this->selector = $this->odm->selector($this->class);
    }

    /**
     * Create new DocumentEntity based on set of provided fields.
     *
     * @final Change static method of entity, not this one.
     * @param array  $fields
     * @param string $class Due ODM models can be inherited you can use this argument to specify
     *                      custom model class.
     * @return DocumentEntity
     */
    final public function create($fields = [], $class = null)
    {
        if (empty($class)) {
            $class = $this->class;
        }

        //Letting entity to create itself (needed
        return call_user_func([$class, 'create'], $fields, $this->odm);
    }

    /**
     * Find document by it's primary key.
     *
     * @see findOne()
     * @param string|\MongoId $id Primary key value.
     * @return DocumentEntity|null
     */
    public function findByPK($id)
    {
        return $this->find()->findByPK($id);
    }

    /**
     * Select one document from mongo collection.
     *
     * @param array $query Fields and conditions to query by.
     * @param array $sortBy
     * @return DocumentEntity|null
     */
    public function findOne(array $query = [], array $sortBy = [])
    {
        return $this->find()->sortBy($sortBy)->findOne($query);
    }

    /**
     * Get associated document selection with pre-configured query (if any).
     *
     * @param array $query
     * @return DocumentSelector
     */
    public function find(array $query = [])
    {
        return $this->selector()->query($query);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->find()->count();
    }

    /**
     * @return DocumentSelector
     */
    final protected function selector()
    {
        //Has to be cloned every time to prevent query collisions
        return clone $this->selector;
    }
}