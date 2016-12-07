<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\Core\Component;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\AccessorInterface;
use Spiral\Models\Events\EntityEvent;
use Spiral\Models\PublishableInterface;
use Spiral\Models\SchematicEntity;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\DocumentException;
use Spiral\ODM\Exceptions\FieldException;
use Spiral\ODM\Exceptions\ODMException;

/**
 * Primary class for spiral ODM, provides ability to pack it's own updates in a form of atomic
 * updates.
 *
 * You can use same properties to configure entity as in DataEntity + schema property.
 *
 * Example:
 *
 * class Test extends DocumentEntity
 * {
 *    private $schema = [
 *       'name' => 'string'
 *    ];
 * }
 *
 * Configuration properties:
 * - schema
 * - defaults
 * - secured (* by default)
 * - fillable
 * - validates
 */
abstract class DocumentEntity extends SchematicEntity implements CompositableInterface
{
    use SaturateTrait;

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
     * Constants used to describe aggregation relations.
     *
     * Example:
     * 'items' => [self::MANY => 'Models\Database\Item', [
     *      'parentID' => 'key::_id'
     * ]]
     *
     * @see odmSchema::$schema
     */
    const MANY = 778;
    const ONE  = 899;

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
     * @var ODMInterface|ODM
     */
    protected $odm = null;

    /**
     * Model schema provided by ODM component.
     *
     * @var array
     */
    protected $odmSchema = [];

    /**
     * {@inheritdoc}
     *
     * @param array|null $schema
     */
    public function __construct(
        $fields,
        ODMInterface $odm = null,
        $schema = null
    ) {
        //We can use global container as fallback if no default values were provided
        $this->odm = $this->saturate($odm, ODMInterface::class);
        $this->odmSchema = !empty($schema) ? $schema : $this->odm->schema(static::class);

        $fields = is_array($fields) ? $fields : [];
        if (!empty($this->odmSchema[ODM::D_DEFAULTS])) {
            /*
             * Merging with default values
             */
            $fields = array_replace_recursive($this->odmSchema[ODM::D_DEFAULTS], $fields);
        }

        parent::__construct($fields, $this->odmSchema);
    }

    /**
     * Change document solid state. SolidState will force document to be saved as one big data set
     * without any atomic operations (dirty fields).
     *
     * @param bool $solidState
     * @param bool $forceUpdate Mark all fields as changed to force update later.
     *
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
     *
     * @return bool
     */
    public function isSolid()
    {
        return $this->solidState;
    }

    /**
     * Check if document has parent.
     *
     * @return bool
     */
    public function isEmbedded()
    {
        return !empty($this->parent);
    }

    /**
     * {@inheritdoc}
     *
     * @todo change to clone
     */
    public function __clone()
    {
        if (empty($this->parent)) {

            //Moving under new parent
            return $this->solidState(true, true);
        }

        /**
         * @var DocumentEntity $document
         */
        $document = new static(
            $this->serializeData(),
            $this->odm,
            $this->odmSchema
        );

        return $document->solidState(true, true);
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($data)
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
        if (!$this->hasField($name)) {
            throw new FieldException("Undefined field '{$name}' in '" . static::class . "'");
        }

        //Original field value
        $original = $this->getField($name, null, false);

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

        $this->setField($offset, null, false);
        if (isset($this->odmSchema[ODM::D_DEFAULTS][$offset])) {
            $this->setField($offset, $this->odmSchema[ODM::D_DEFAULTS][$offset], false);
        }
    }

    /**
     * Alias for atomic operation $set. Attention, this operation is not identical to setField()
     * method, it performs low level operation and can be used only on simple fields. No filters
     * will be applied to field!
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return $this
     *
     * @throws DocumentException
     */
    public function set($field, $value)
    {
        if ($this->hasUpdates($field, true)) {
            throw new FieldException(
                "Unable to apply multiple atomic operation to field '{$field}'"
            );
        }

        $this->setField($field, $value);

        //Filtered
        $this->atomics[self::ATOMIC_SET][$field] = $this->getFields($value);

        return $this;
    }

    /**
     * Alias for atomic operation $inc.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     *
     * @throws DocumentException
     */
    public function inc($field, $value)
    {
        if ($this->hasUpdates($field, true) && !isset($this->atomics['$inc'][$field])) {
            throw new FieldException(
                "Unable to apply multiple atomic operation to field '{$field}'"
            );
        }

        if (!isset($this->atomics['$inc'][$field])) {
            $this->atomics['$inc'][$field] = 0;
        }

        $this->atomics['$inc'][$field] += $value;
        $this->setField($field, $this->getField($field) + $value);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function defaultValue()
    {
        return $this->odmSchema[ODM::D_DEFAULTS];
    }

    /**
     * {@inheritdoc}
     *
     * Include every composition public data into result.
     */
    public function publicFields()
    {
        $result = [];

        foreach ($this->getKeys() as $field) {
            if (in_array($field, $this->odmSchema[ODM::D_HIDDEN])) {
                //We might need to use isset in future, for performance
                continue;
            }

            /*
             * @var mixed|array|DocumentAccessorInterface|CompositableInterface
             */
            $value = $this->getField($field);

            if ($value instanceof PublishableInterface) {
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

        return $result;
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

            foreach ($this->getFields(false) as $value) {
                if ($value instanceof DocumentAccessorInterface && $value->hasUpdates()) {
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

        $value = $this->getField($field);
        if ($value instanceof DocumentAccessorInterface && $value->hasUpdates()) {
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

        foreach ($this->getFields(false) as $value) {
            if ($value instanceof DocumentAccessorInterface) {
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

        foreach ($this->getFields(false) as $field => $value) {
            if ($field == '_id') {
                continue;
            }

            if ($value instanceof DocumentAccessorInterface) {
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
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'fields'  => $this->getFields(),
            'atomics' => $this->hasUpdates() ? $this->buildAtomics() : []
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
        $options = null;
        if (is_array($accessor)) {
            list($accessor, $options) = $accessor;
        }

        if ($accessor == ODM::CMP_ONE) {
            //Pointing to document instance
            return $this->odm->document($options, $value);
        }

        //Additional options are supplied for CompositableInterface
        return new $accessor($value, $this->odm, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function iocContainer()
    {
        if (empty($this->odm) || !$this->odm instanceof Component) {
            return parent::iocContainer();
        }

        return $this->odm->iocContainer();
    }

    /**
     * Create document entity using given ODM instance or load parent ODM via shared container.
     *
     * @see   Component::staticContainer()
     *
     * @param array        $fields Model fields to set, will be passed thought filters.
     * @param ODMInterface $odm    ODMInterface component, global container will be called if not
     *                             instance provided.
     *
     * @return DocumentEntity
     *
     * @event created($document)
     */
    public static function create($fields = [], ODMInterface $odm = null)
    {
        /**
         * @var DocumentEntity $document
         */
        $document = new static([], null, $odm);

        //Forcing validation (empty set of fields is not valid set of fields)
        $document->setFields($fields)->dispatch('created', new EntityEvent($document));

        return $document;
    }

    /**
     * Called by ODM with set of loaded fields. Must return name of appropriate class.
     *
     * @param array        $fields
     * @param ODMInterface $odm
     *
     * @return string
     *
     * @throws DefinitionException
     */
    public static function defineClass(array $fields, ODMInterface $odm)
    {
        throw new DefinitionException('Class definition method has not been implemented');
    }
}