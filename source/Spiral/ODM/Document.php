<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\Models\AccessorInterface;
use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\EntityInterface;
use Spiral\ODM\Entities\Collection;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\DocumentException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\ODM\Traits\FindTrait;

/**
 * DocumentEntity with added ActiveRecord methods and association with collection.
 *
 * Document also provides an ability to specify aggregations using it's schema:
 *
 * protected $schema = [
 *     ...,
 *     'outer' => [self::ONE => Outer::class, [   //Reference to outer document using internal
 *          '_id' => 'self::outerID'              //outerID value
 *     ]],
 *     'many' => [self::MANY => Outer::class, [   //Reference to many outer document using
 *          'innerID' => 'self::_id'              //document primary key
 *     ]]
 * ];
 *
 * Note: self::{name} construction will be replaced with document value in resulted query, even
 * in case of arrays ;) You can also use dot notation to get value from nested document.
 *
 * @var array
 */
abstract class Document extends DocumentEntity implements ActiveEntityInterface
{
    /**
     * Static functions.
     */
    use FindTrait;

    /**
     * Indication that save methods must be validated by default, can be altered by calling save
     * method with user arguments.
     */
    const VALIDATE_SAVE = true;

    /**
     * Collection name where document should be stored into.
     *
     * @var string
     */
    protected $collection = null;

    /**
     * Database name/id where document related collection located in.
     *
     * @var string|null
     */
    protected $database = null;

    /**
     * Set of indexes to be created for associated collection. Use self::INDEX_OPTIONS or "@options"
     * for additional parameters.
     *
     * Example:
     * protected $indexes = [
     *      ['email' => 1, '@options' => ['unique' => true]],
     *      ['name' => 1]
     * ];
     *
     * @link http://php.net/manual/en/mongocollection.ensureindex.php
     * @var array
     */
    protected $indexes = [];

    /**
     * @see Component::staticContainer()
     * @param array           $fields
     * @param EntityInterface $parent
     * @param ODM             $odm
     * @param array           $odmSchema
     */
    public function __construct(
        $fields = [],
        EntityInterface $parent = null,
        ODM $odm = null,
        $odmSchema = null
    ) {
        parent::__construct($fields, $parent, $odm, $odmSchema);

        if ((!$this->isLoaded() && !$this->isEmbedded())) {
            //Document is newly created instance
            $this->solidState(true)->invalidate();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return \MongoId|null
     */
    public function primaryKey()
    {
        return isset($this->fields['_id']) ? $this->fields['_id'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function isLoaded()
    {
        return (bool)$this->primaryKey();
    }

    /**
     * {@inheritdoc}
     *
     * Create or update document data in database.
     *
     * @param bool|null $validate Overwrite default option declared in VALIDATE_SAVE to force or
     *                            disable validation before saving.
     * @throws DocumentException
     * @event saving()
     * @event saved()
     * @event updating()
     * @event updated()
     */
    public function save($validate = null)
    {
        $validate = !is_null($validate) ? $validate : static::VALIDATE_SAVE;

        if ($validate && !$this->isValid()) {
            //Using default model behaviour
            return false;
        }

        if ($this->isEmbedded()) {
            throw new DocumentException(
                "Embedded document '" . get_class($this) . "' can not be saved into collection."
            );
        }

        if (!$this->isLoaded()) {
            $this->fire('saving');
            unset($this->fields['_id']);

            //Create new document
            $this->odmCollection($this->odm)->insert($this->fields = $this->serializeData());

            $this->fire('saved');
        } elseif ($this->isSolid() || $this->hasUpdates()) {
            $this->fire('updating');

            //Update existed document
            $this->odmCollection($this->odm)->update(
                ['_id' => $this->primaryKey()],
                $this->buildAtomics()
            );

            $this->fire('updated');
        }

        $this->flushUpdates();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws DocumentException
     * @event deleting()
     * @event deleted()
     */
    public function delete()
    {
        if ($this->isEmbedded()) {
            throw new DocumentException(
                "Embedded document '" . get_class($this) . "' can not be deleted from collection."
            );
        }

        $this->fire('deleting');
        if ($this->isLoaded()) {
            $this->odmCollection($this->odm)->remove(['_id' => $this->primaryKey()]);
        }
        $this->fields = $this->odmSchema()[ODM::D_DEFAULTS];
        $this->fire('deleted');
    }

    /**
     * {@inheritdoc} See DataEntity class.
     *
     * ODM: Get instance of Collection or Document associated with described aggregation.
     *
     * Example:
     * $parentGroup = $user->group();
     * echo $user->posts()->where(['published' => true])->count();
     *
     * @return mixed|AccessorInterface|Collection|Document[]|Document
     * @throws DocumentException
     */
    public function __call($offset, array $arguments)
    {
        if (!isset($this->odmSchema()[ODM::D_AGGREGATIONS][$offset])) {
            //Field getter/setter
            return parent::__call($offset, $arguments);
        }

        return $this->aggregate($offset);
    }

    /**
     * Get document aggregation.
     *
     * @param string $aggregation
     * @return Collection|Document
     */
    public function aggregate($aggregation)
    {
        if (!isset($this->odmSchema()[ODM::D_AGGREGATIONS][$aggregation])) {
            throw new DocumentException("Undefined aggregation '{$aggregation}'.");
        }

        $aggregation = $this->odmSchema()[ODM::D_AGGREGATIONS][$aggregation];

        //Query preparations
        $query = $this->interpolateQuery($aggregation[ODM::AGR_QUERY]);

        //Every aggregation works thought ODM collection
        $collection = $this->odm->odmCollection($aggregation[ODM::ARG_CLASS])->query($query);

        //In future i might need separate class to represent aggregation
        if ($aggregation[ODM::AGR_TYPE] == self::ONE) {
            return $collection->findOne();
        }

        return $collection;
    }

    /**
     * @return Object
     */
    public function __debugInfo()
    {
        if (empty($this->collection)) {
            return (object)[
                'fields'  => $this->getFields(),
                'atomics' => $this->hasUpdates() ? $this->buildAtomics() : [],
                'errors'  => $this->getErrors()
            ];
        }

        return (object)[
            'collection' => $this->odmSchema()[ODM::D_DB] . '/' . $this->collection,
            'fields'     => $this->getFields(),
            'atomics'    => $this->hasUpdates() ? $this->buildAtomics() : [],
            'errors'     => $this->getErrors()
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Accessor options include field type resolved by DocumentSchema.
     *
     * @throws ODMException
     * @throws DefinitionException
     */
    protected function createAccessor($accessor, $value)
    {
        $accessor = parent::createAccessor($accessor, $value);

        if ($accessor instanceof CompositableInterface && !$this->isLoaded() && !$this->isEmbedded()) {
            //Newly created object
            $accessor->invalidate();
        }

        return $accessor;
    }

    /**
     * Interpolate aggregation query with document values.
     *
     * @param array $query
     * @return array
     */
    protected function interpolateQuery(array $query)
    {
        $fields = $this->fields;
        array_walk_recursive($query, function (&$value) use ($fields) {
            if (strpos($value, 'self::') === 0) {
                $value = $this->dotGet(substr($value, 6));
            }
        });

        return $query;
    }

    /**
     * Get field value using dot notation.
     *
     * @param string $name
     * @return mixed|null
     */
    private function dotGet($name)
    {
        /**
         * @var EntityInterface|AccessorInterface|array $source
         */
        $source = $this;

        $path = explode('.', $name);
        foreach ($path as $step) {
            if ($source instanceof EntityInterface) {
                if (!$source->hasField($step)) {
                    return null;
                }

                //Sub entity
                $source = $source->getField($step);
                continue;
            }

            if ($source instanceof AccessorInterface) {
                $source = $source->serializeData();
                continue;
            }

            if (is_array($source) && array_key_exists($step, $source)) {
                $source = &$source[$step];
                continue;
            }

            //Unable to resolve value, an exception required here
            return null;
        }

        return $source;
    }
}