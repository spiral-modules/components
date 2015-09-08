<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM;

use Spiral\Core\ContainerInterface;
use Spiral\Models\AccessorInterface;
use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\DataEntity;
use Spiral\ODM\Entities\Collection;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\DocumentException;
use Spiral\ODM\Exceptions\ODMException;

/**
 * Document is base data model for ODM component, it used to describe Mongo document schema,
 * filters, validations and etc. In addition Document classes used as ActiveRecord pattern to
 * represent Mongo data. ODM component will automatically analyze existed Documents and create
 * cached version of their schema.
 */
class Document extends DataEntity implements CompositableInterface, ActiveEntityInterface
{
    /**
     * We are going to inherit parent validation rules, this will let spiral translator know about
     * it and merge i18n messages.
     *
     * @see TranslatorTrait
     */
    const I18N_INHERIT_MESSAGES = true;

    /**
     * Indication that save methods must be validated by default, can be altered by calling save
     * method with user arguments.
     */
    const VALIDATE_SAVE = true;

    /**
     * Helper constant to identify atomic SET operations.
     */
    const ATOMIC_SET = '$set';

    /**
     * Tells ODM component that Document class must be resolved using document fields. ODM must
     * match fields to every child of this documents and find best match. This is default definition
     * behaviour.
     *
     * Example:
     * > Class A: _id, name, address
     * > Class B extends A: _id, name, address, email
     * < Class B will be used to represent all documents with existed email field.
     *
     * @see DocumentSchema
     */
    const DEFINITION_FIELDS = 1;

    /**
     * Tells ODM that logical method (defineClass) must be used to define document class. Method
     * will receive document fields as input and must return document class name.
     *
     * Example:
     * > Class A: _id, name, type (a)
     * > Class B extends A: _id, name, type (b)
     * > Class C extends B: _id, name, type (c)
     * < Static method in class A (parent) should return A, B or C based on type field value (as
     * example).
     *
     * Attention, ODM will always ask TOP PARENT (in collection) to define class when you loading
     * documents from collections.
     *
     * @see defineClass($fields)
     * @see DocumentSchema
     */
    const DEFINITION_LOGICAL = 2;

    /**
     * Indication to ODM component of method to resolve Document class using it's fieldset. This
     * constant is required due Document can inherit another Document.
     */
    const DEFINITION = self::DEFINITION_FIELDS;

    /**
     * Automatically convert "_id" to "id" in publicFields() method.
     */
    const REMOVE_ID_UNDERSCORE = true;

    /**
     * Additional index options must be located under this key.
     */
    const INDEX_OPTIONS = '@options';

    /**
     * Constants used to describe aggregation relations.
     *
     * Example:
     * 'items' => [self::MANY => 'Models\Database\Item', [
     *      'parentID' => 'key::_id'
     * ]]
     *
     * @see Document::$schema
     */
    const MANY = 778;
    const ONE  = 899;

    /**
     * Errors in nested documents and acessors.
     *
     * @var array
     */
    private $nestedErrors = [];

    /**
     * Model schema provided by ODM compoent.
     *
     * @var array
     */
    private $odmSchema = [];

    /**
     * SolidState will force document to be saved as one big data set without any atomic operations
     * (dirty fields).
     *
     * @var bool
     */
    private $solidState = false;

    /**
     * Document field updates (changed values).
     *
     * @var array
     */
    private $updates = [];

    /**
     * User specified set of atomic operation to be applied to document on save() call.
     *
     * @var array
     */
    private $atomics = [];

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
     * Document fields, accessors and relations. ODM will generate setters and getters for some
     * fields based on their types.
     *
     * Example, fields:
     * protected $schema = [
     *      '_id'    => 'MongoId', //Primary key field
     *      'value'  => 'string',  //Default string field
     *      'values' => ['string'] //ScalarArray accessor will be applied for fields like that
     * ];
     *
     * Compositions:
     * protected $schema = [
     *     ...,
     *     'child' => Child::class,  //One document are composited, for example user Profile
     *     'many'  => [Child::class] //Compositor accessor will be applied, allows to composite
     *     many
     *                               //document intances
     * ];
     *
     * Attention, every composited class will be validated with it's parent document!
     *
     * Aggregations:
     * protected $schema = [
     *     ...,
     *     'outer' => [self::ONE => Outer::class, [   //Reference to outer document using internal
     *          '_id' => 'key::outerID'               //outerID value
     *     ]],
     *     'many' => [self::MANY => Outer::class, [   //Reference to many outer document using
     *          'innerID' => 'key::_id'               //document primary key
     *     ]]
     * ];
     *
     * Note: key::{name} construction will be replaced with document value in resulted query, even
     * in case of arrays ;)
     *
     * Documents can extend each other, in this case schema will also be inherited.
     *
     * @var array
     */
    protected $schema = [];

    /**
     * Default field values.
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * @invisible
     * @var CompositableInterface|Document|DataEntity
     */
    protected $parent = null;

    /**
     * @invisible
     * @var ODM
     */
    protected $odm = null;

    /**
     * @see Component::staticContainer()
     * @param array                                     $fields
     * @param CompositableInterface|Document|DataEntity $parent
     * @param ODM                                       $odm
     * @param array                                     $odmSchema
     */
    public function __construct($fields = [], $parent = null, ODM $odm = null, $odmSchema = null)
    {
        $this->parent = $parent;

        if (empty($odm)) {
            //Only when global container is set
            $odm = ODM::instance();
        }

        $this->odm = $odm;
        $this->odmSchema = !empty($odmSchema) ? $odmSchema : $this->odm->getSchema(static::class);

        static::initialize();

        $this->fields = is_array($fields) ? $fields : [];
        if (!empty($this->odmSchema[ODM::D_DEFAULTS])) {
            $this->fields = array_replace_recursive($this->odmSchema[ODM::D_DEFAULTS], $fields);
        }

        if ((!$this->isLoaded() && !$this->isEmbedded()) || empty($fields)) {
            //Document is newly created instance
            $this->solidState(true)->invalidate();
        }
    }

    /**
     * Change document solid state. SolidState will force document to be saved as one big data set
     * without any atomic operations (dirty fields).
     *
     * @param bool $solidState
     * @param bool $forceUpdate Mark all fields as changed to force update later.
     * @return $this
     */
    public function solidState($solidState, $forceUpdate = false)
    {
        $this->solidState = $solidState;

        if ($forceUpdate) {
            $this->updates = $this->odmSchema[ODM::D_DEFAULTS];
        }

        return $this;
    }

    /**
     * Is document is solid state?
     *
     * @see solidState()
     * @return bool
     */
    public function isSolid()
    {
        return $this->solidState;
    }

    /**
     * Document primary key value (if any).
     *
     * @return null|\MongoId
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
     * Check if document has parent.
     *
     * @return bool
     */
    public function isEmbedded()
    {
        return (bool)$this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function embed($parent)
    {
        if (empty($this->parent)) {
            $this->parent = $parent;

            //Moving under new parent
            return $this->solidState(true, true);
        }

        if ($parent === $this->parent) {
            return $this;
        }

        /**
         * @var Document $document
         */
        $document = new static($this->serializeData(), $parent, $this->odm, $this->odmSchema);

        return $document->solidState(true, true)->invalidate(true);
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data)
    {
        return $this->setFields($data);
    }

    /**
     * {@inheritdoc}
     *
     * Must track field updates.
     */
    public function setField($name, $value, $filter = true)
    {
        $original = isset($this->fields[$name]) ? $this->fields[$name] : null;
        parent::setField($name, $value, $filter);

        if (!array_key_exists($name, $this->updates)) {
            $this->updates[$name] = $original instanceof AccessorInterface
                ? $original->serializeData()
                : $original;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Will restore default value if presented.
     */
    public function __unset($offset)
    {
        if (!array_key_exists($offset, $this->updates)) {
            //Let document know that field value changed, but without overwriting previous change
            $this->updates[$offset] = isset($this->odmSchema[ODM::D_DEFAULTS][$offset])
                ? $this->odmSchema[ODM::D_DEFAULTS][$offset]
                : null;
        }

        $this->fields[$offset] = null;
        if (isset($this->odmSchema[ODM::D_DEFAULTS][$offset])) {
            //Restoring default value if presented (required for typecasting)
            $this->fields[$offset] = $this->odmSchema[ODM::D_DEFAULTS][$offset];
        }
    }

    /**
     * Alias for atomic operation $set. Attention, this operation is not identical to setField()
     * method, it performs low level operation and can be used only on simple fields. No filters
     * will be applied to field!
     *
     * @param string $field
     * @param mixed  $value
     * @return $this
     * @throws DocumentException
     */
    public function set($field, $value)
    {
        if ($this->hasUpdates($field, true)) {
            throw new DocumentException(
                "Unable to apply multiple atomic operation to field '{$field}'."
            );
        }

        $this->atomics[self::ATOMIC_SET][$field] = $value;
        $this->fields[$field] = $value;

        return $this;
    }

    /**
     * Alias for atomic operation $inc.
     *
     * @param string $field
     * @param string $value
     * @return $this
     * @throws DocumentException
     */
    public function inc($field, $value)
    {
        if ($this->hasUpdates($field, true) && !isset($this->atomics['$inc'][$field])) {
            throw new DocumentException(
                "Unable to apply multiple atomic operation to field '{$field}'."
            );
        }

        if (!isset($this->atomics['$inc'][$field])) {
            $this->atomics['$inc'][$field] = 0;
        }

        $this->atomics['$inc'][$field] += $value;
        $this->fields[$field] += $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Include every composition public data into result.
     */
    public function publicFields()
    {
        $result = [];

        foreach ($this->fields as $field => $value) {
            if (in_array($field, $this->odmSchema[ODM::D_HIDDEN])) {
                //We might need to use isset in future, for performance
                continue;
            }

            /**
             * @var mixed|array|EmbeddableInterface|CompositableInterface
             */
            $value = $this->getField($field);

            if ($value instanceof CompositableInterface) {
                $result[$field] = $value->publicFields();
                continue;
            }

            if ($value instanceof \MongoId) {
                $value = (string)$value;
            }

            if (is_array($value)) {
                array_walk_recursive($value, function (&$value) {
                    if ($value instanceof \MongoId) {
                        $value = (string)$value;
                    }
                });
            }

            if (static::REMOVE_ID_UNDERSCORE && $field == '_id') {
                $field = 'id';
            }

            $result[$field] = $value;
        }

        return $this->fire('publicFields', $result);
    }

    /**
     * {@inheritdoc}
     */
    public function validator(array $rules = [], ContainerInterface $container = null)
    {
        //Initiate validation using rules declared in odmSchema
        return parent::validator(
            !empty($rules) ? $rules : $this->odmSchema[ODM::D_VALIDATES],
            $container
        );
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
        } elseif ($this->solidState || $this->hasUpdates()) {
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
        $this->isLoaded() && $this->odmCollection($this->odm)->remove(['_id' => $this->primaryKey()]);
        $this->fields = $this->odmSchema[ODM::D_DEFAULTS];
        $this->fire('deleted');
    }

    /**
     * {@inheritdoc}
     *
     * @param string $field       Specific field name to check for updates.
     * @param bool   $atomicsOnly Check if field has any atomic operation associated with.
     */
    public function hasUpdates($field = null, $atomicsOnly = false)
    {
        if (empty($field)) {
            if (!empty($this->updates) || !empty($this->atomics)) {
                return true;
            }

            foreach ($this->fields as $field => $value) {
                if ($value instanceof EmbeddableInterface && $value->hasUpdates()) {
                    return true;
                }
            }

            return false;
        }

        foreach ($this->atomics as $operations) {
            if (array_key_exists($field, $operations)) {
                //Property already changed by atomic operation
                return true;
            }
        }

        if ($atomicsOnly) {
            return false;
        }

        if (array_key_exists($field, $this->updates)) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        $this->updates = $this->atomics = [];

        foreach ($this->fields as $value) {
            if ($value instanceof EmbeddableInterface) {
                $value->flushUpdates();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildAtomics($container = '')
    {
        if (!$this->hasUpdates() && !$this->isSolid()) {
            return [];
        }

        if ($this->isSolid()) {
            if (!empty($container)) {
                //Simple nested document in solid state
                return [self::ATOMIC_SET => [$container => $this->serializeData()]];
            }

            //Direct document save
            $atomics = [self::ATOMIC_SET => $this->serializeData()];
            unset($atomics[self::ATOMIC_SET]['_id']);

            return $atomics;
        }

        if (empty($container)) {
            $atomics = $this->atomics;
        } else {
            $atomics = [];

            foreach ($this->atomics as $atomic => $fields) {
                foreach ($fields as $field => $value) {
                    $atomics[$atomic][$container . '.' . $field] = $value;
                }
            }
        }

        foreach ($this->fields as $field => $value) {
            if ($field == '_id') {
                continue;
            }

            if ($value instanceof EmbeddableInterface) {
                $atomics = array_merge_recursive(
                    $atomics,
                    $value->buildAtomics(($container ? $container . '.' : '') . $field)
                );

                continue;
            }

            foreach ($atomics as $atomic => $operations) {
                if (array_key_exists($field, $operations) && $atomic != self::ATOMIC_SET) {
                    //Property already changed by atomic operation
                    continue;
                }
            }

            if (array_key_exists($field, $this->updates)) {
                //Generating set operation for changed field
                $atomics[self::ATOMIC_SET][($container ? $container . '.' : '') . $field] = $value;
            }
        }

        return $atomics;
    }

    /**
     * {@inheritdoc} See DataEntity class.
     *
     * ODM:
     * Get instance of Collection or Document associated with described aggregation. Use method
     * arguments to specify additional query.
     *
     * Example:
     * $parentGroup = $user->group();
     * echo $user->posts(['published' => true])->count();
     *
     * @return mixed|AccessorInterface|Collection|Document[]|Document
     * @throws DocumentException
     */
    public function __call($offset, array $arguments)
    {
        if (!isset($this->odmSchema[ODM::D_AGGREGATIONS][$offset])) {
            return parent::__call($offset, $arguments);
        }

        $aggregation = $this->odmSchema[ODM::D_AGGREGATIONS][$offset];

        //Query preparations
        $query = $this->interpolateQuery($aggregation[ODM::AGR_QUERY]);

        if (isset($arguments[0]) && is_array($arguments[0])) {
            //Clarifying query
            $query = array_merge($query, $arguments[0]);
        }

        $collection = new Collection($this->odm, $aggregation[ODM::AGR_DB],
            $aggregation[ODM::AGR_COLLECTION], $query);
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
            'collection' => $this->odmSchema[ODM::D_DB] . '/' . $this->collection,
            'fields'     => $this->getFields(),
            'atomics'    => $this->hasUpdates() ? $this->buildAtomics() : [],
            'errors'     => $this->getErrors()
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Will invalidate every composited object.
     *
     * @param bool $soft Do not invalidate composited documents.
     * @return $this
     */
    public function invalidate($soft = true)
    {
        parent::invalidate();

        if ($soft) {
            return $this;
        }

        //Invalidating all compositions
        foreach ($this->odmSchema[ODM::D_COMPOSITIONS] as $field) {
            //Let's force composition construction
            $composition = $this->getField($field);
            if (!$composition instanceof CompositableInterface) {
                throw new DocumentException(
                    "All compositions must be an instance of CompositableInterface."
                );
            }

            $composition->invalidate();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        $this->validate();

        return empty($this->errors) && empty($this->nestedErrors);
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($reset = false)
    {
        return parent::getErrors($reset) + $this->nestedErrors;
    }

    /**
     * {@inheritdoc}
     */
    protected function container()
    {
        return $this->odm->container();
    }

    /**
     * {@inheritdoc}
     *
     * Will validate every CompositableInterface instance.
     *
     * @param bool $reset
     * @throws DocumentException
     */
    protected function validate($reset = false)
    {
        $this->nestedErrors = [];

        //Validating all compositions
        foreach ($this->odmSchema[ODM::D_COMPOSITIONS] as $field) {
            $composition = $this->getField($field);
            if (!$composition instanceof CompositableInterface) {
                //Something weird.
                continue;
            }

            if (!$composition->isValid()) {
                $this->nestedErrors[$field] = $composition->getErrors($reset);
            }
        }

        parent::validate($reset);

        return empty($this->errors + $this->nestedErrors);
    }

    /**
     * {@inheritdoc}
     */
    protected function isFillable($field)
    {
        if (!empty($this->odmSchema[ODM::D_FILLABLE])) {
            return in_array($field, $this->odmSchema[ODM::D_FILLABLE]);
        }

        if ($this->odmSchema[ODM::D_SECURED] === '*') {
            return false;
        }

        return !in_array($field, $this->odmSchema[ODM::D_SECURED]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getMutator($field, $mutator)
    {
        if (isset($this->odmSchema[ODM::D_MUTATORS][$mutator][$field])) {
            return $this->odmSchema[ODM::D_MUTATORS][$mutator][$field];
        }

        return null;
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
        $options = null;
        if (is_array($accessor)) {
            list($accessor, $options) = $accessor;
        }

        if ($accessor == ODM::CMP_ONE) {
            //Pointing to document instance
            $accessor = $this->odm->document($options, $value, $this);
        } else {
            $accessor = new $accessor($value, $this, $this->odm, $options);
        }

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
            if (strpos($value, 'key::') === 0) {
                $value = $fields[substr($value, 5)];
                if ($value instanceof EmbeddableInterface) {
                    $value = $value->serializeData();
                }
            }
        });

        return $query;
    }

    /**
     * {@inheritdoc}
     *
     * @see   Component::staticContainer()
     * @param array $fields Model fields to set, will be passed thought filters.
     * @param ODM   $odm    ODM component, global container will be called if not instance provided.
     * @event created()
     */
    public static function create($fields = [], ODM $odm = null)
    {
        if (empty($odm)) {
            //Only when global container is set
            $odm = ODM::instance();
        }

        /**
         * @var Document $document
         */
        $document = new static([], null, $odm);

        //Forcing validation (empty set of fields is not valid set of fields)
        $document->setFields($fields)->fire('created');

        return $document;
    }

    /**
     * Find multiple documents based on provided query.
     *
     * @param mixed $query Fields and conditions to filter by.
     * @return Collection
     * @throws ODMException
     */
    public static function find(array $query = [])
    {
        return static::odmCollection()->query($query);
    }

    /**
     * Find one document based on provided query and sorting.
     *
     * @param array $query  Fields and conditions to filter by.
     * @param array $sortBy Sorting.
     * @return static|null
     * @throws ODMException
     */
    public static function findOne(array $query = [], array $sortBy = [])
    {
        return static::find($query)->sortBy($sortBy)->findOne();
    }

    /**
     * Find document using it's primary key.
     *
     * @param mixed $mongoID Valid MongoId, string value must be automatically converted to MongoId
     *                       object.
     * @return static|null
     * @throws ODMException
     */
    public static function findByPK($mongoID)
    {
        if (!$mongoID = ODM::mongoID($mongoID)) {
            return null;
        }

        return static::findOne(['_id' => $mongoID]);
    }

    /**
     * Instance of ODM Collection associated with specific document.
     *
     * @see   Component::staticContainer()
     * @param ODM $odm ODM component, global container will be called if not instance provided.
     * @return Collection
     * @throws ODMException
     * @event collection(Collection $collection)
     */
    public static function odmCollection(ODM $odm = null)
    {
        if (empty($odm)) {
            //Only when global container is set
            $odm = ODM::instance();
        }

        //Ensure traits
        static::initialize();

        return self::events()->fire('collection', $odm->odmCollection(static::class));
    }

    /**
     * Called by ODM with set of loaded fields. Must return name of appropriate class.
     *
     * @param array $fields
     * @return string
     * @throws DefinitionException
     */
    public static function defineClass(array $fields)
    {
        throw new DefinitionException("Class definition methods was not implemented.");
    }
}