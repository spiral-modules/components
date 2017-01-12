<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

use MongoDB\BSON\ObjectID;
use Spiral\Core\Component;
use Spiral\Core\Exceptions\ScopeException;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\AccessorInterface;
use Spiral\Models\SchematicEntity;
use Spiral\Models\Traits\SolidableTrait;
use Spiral\ODM\Entities\DocumentCompositor;
use Spiral\ODM\Entities\DocumentInstantiator;
use Spiral\ODM\Exceptions\AccessorException;
use Spiral\ODM\Exceptions\AggregationException;
use Spiral\ODM\Exceptions\DocumentException;
use Spiral\ODM\Exceptions\FieldException;
use Spiral\ODM\Helpers\AggregationHelper;
use Spiral\ODM\Schemas\Definitions\CompositionDefinition;

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
 *    const SCHEMA = [
 *       'name' => 'string'
 *    ];
 * }
 *
 * Configuration properties:
 * - schema
 * - defaults
 * - secured (* by default)
 * - fillable
 */
abstract class DocumentEntity extends SchematicEntity implements CompositableInterface
{
    use SaturateTrait, SolidableTrait;

    /**
     * Set of schema sections needed to describe entity behaviour.
     */
    const SH_INSTANTIATION = 0;
    const SH_DEFAULTS      = 1;
    const SH_COMPOSITIONS  = 6;
    const SH_AGGREGATIONS  = 7;

    /**
     * Constants used to describe aggregation relations (also used internally to identify
     * composition).
     *
     * Example:
     * 'items' => [self::MANY => Item::class, ['parentID' => 'key::_id']]
     *
     * @see DocumentEntity::SCHEMA
     */
    const MANY = 778;
    const ONE  = 899;

    /**
     * Class responsible for instance construction.
     */
    const INSTANTIATOR = DocumentInstantiator::class;

    /**
     * Document fields, accessors and relations. ODM will generate setters and getters for some
     * fields based on their types.
     *
     * Example, fields:
     * const SCHEMA = [
     *      '_id'    => 'MongoId', //Primary key field
     *      'value'  => 'string',  //Default string field
     *      'values' => ['string'] //ScalarArray accessor will be applied for fields like that
     * ];
     *
     * Compositions:
     * const SCHEMA = [
     *     ...,
     *     'child'       => Child::class,   //One document are composited, for example user Profile
     *     'many'        => [Child::class]  //Compositor accessor will be applied, allows to
     *                                      //composite many document instances
     * ];
     *
     * Documents can extend each other, in this case schema will also be inherited.
     *
     * Attention, make sure you properly set FILLABLE option in parent class to use constructions
     * like:
     * $parent->child = [...];
     *
     * or
     * $parent->setFields(['child'=>[...]]);
     *
     * @var array
     */
    const SCHEMA = [];

    /**
     * Default field values.
     *
     * @var array
     */
    const DEFAULTS = [];

    /**
     * Model behaviour configurations.
     */
    const SECURED   = '*';
    const HIDDEN    = [];
    const FILLABLE  = [];
    const SETTERS   = [];
    const GETTERS   = [];
    const ACCESSORS = [];

    /**
     * Document behaviour schema.
     *
     * @var array
     */
    private $documentSchema = [];

    /**
     * Document field updates (changed values).
     *
     * @var array
     */
    private $changes = [];

    /**
     * Parent ODM instance, responsible for aggregations and lazy loading operations.
     *
     * @invisible
     * @var ODMInterface
     */
    protected $odm;

    /**
     * {@inheritdoc}
     *
     * @param ODMInterface $odm To lazy create nested document ang aggregations.
     *
     * @throws ScopeException When no ODM instance can be resolved.
     */
    public function __construct(array $fields = [], ODMInterface $odm = null, array $schema = null)
    {
        //We can use global container as fallback if no default values were provided
        $this->odm = $this->saturate($odm, ODMInterface::class);

        //Use supplied schema or fetch one from ODM
        $this->documentSchema = !empty($schema) ? $schema : $this->odm->define(
            static::class,
            ODMInterface::D_SCHEMA
        );

        $fields = is_array($fields) ? $fields : [];
        if (!empty($this->documentSchema[self::SH_DEFAULTS])) {
            //Merging with default values
            $fields = array_replace_recursive($this->documentSchema[self::SH_DEFAULTS], $fields);
        }

        parent::__construct($fields, $this->documentSchema);
    }

    /**
     * {@inheritdoc}
     */
    public function getField(string $name, $default = null, bool $filter = true)
    {
        if (!$this->hasField($name) && !isset($this->documentSchema[self::SH_COMPOSITIONS][$name])) {
            throw new FieldException(sprintf(
                "No such property '%s' in '%s', check schema being relevant",
                $name,
                get_called_class()
            ));
        }

        return parent::getField($name, $default, $filter);
    }

    /**
     * {@inheritdoc}
     *
     * Tracks field changes.
     */
    public function setField(string $name, $value, bool $filter = true)
    {
        if (!$this->hasField($name)) {
            //We are only allowing to modify existed fields, this is strict schema
            throw new FieldException(sprintf(
                "No such property '%s' in '%s', check schema being relevant",
                $name,
                get_called_class()
            ));
        }

        $this->registerChange($name);

        parent::setField($name, $value, $filter);
    }

    /**
     * {@inheritdoc}
     *
     * Will restore default value if presented.
     */
    public function __unset($offset)
    {
        if (!$this->isNullable($offset)) {
            throw new FieldException("Unable to unset not nullable field '{$offset}'");
        }

        $this->setField($offset, null, false);
    }

    /**
     * Provides ability to invoke document aggregation.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed|null|AccessorInterface|CompositableInterface|Document|Entities\DocumentSelector
     */
    public function __call($method, array $arguments)
    {
        if (isset($this->documentSchema[self::SH_AGGREGATIONS][$method])) {
            if (!empty($arguments)) {
                throw new AggregationException("Aggregation method call except 0 parameters");
            }

            $helper = new AggregationHelper($this, $this->odm);

            return $helper->createAggregation($method);
        }

        throw new DocumentException("Undefined method call '{$method}' in '" . get_called_class() . "'");
    }

    /**
     * {@inheritdoc}
     *
     * @param string $field Check once specific field changes.
     */
    public function hasUpdates(string $field = null): bool
    {
        //Check updates for specific field
        if (!empty($field)) {
            if (array_key_exists($field, $this->changes)) {
                return true;
            }

            //Do not force accessor creation
            $value = $this->getField($field, null, false);
            if ($value instanceof CompositableInterface && $value->hasUpdates()) {
                return true;
            }

            return false;
        }

        if (!empty($this->changes)) {
            return true;
        }

        //Do not force accessor creation
        foreach ($this->getFields(false) as $value) {
            //Checking all fields for changes (handled internally)
            if ($value instanceof CompositableInterface && $value->hasUpdates()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function buildAtomics(string $container = null): array
    {
        if (!$this->hasUpdates() && !$this->isSolid()) {
            return [];
        }

        if ($this->isSolid()) {
            if (!empty($container)) {
                //Simple nested document in solid state
                return ['$set' => [$container => $this->packValue(false)]];
            }

            //No parent container
            return ['$set' => $this->packValue(false)];
        }

        //Aggregate atomics from every nested composition
        $atomics = [];

        foreach ($this->getFields(false) as $field => $value) {
            if ($value instanceof CompositableInterface) {
                $atomics = array_merge_recursive(
                    $atomics,
                    $value->buildAtomics((!empty($container) ? $container . '.' : '') . $field)
                );

                continue;
            }

            foreach ($atomics as $atomic => $operations) {
                if (array_key_exists($field, $operations) && $atomic != '$set') {
                    //Property already changed by atomic operation
                    continue;
                }
            }

            if (array_key_exists($field, $this->changes)) {
                //Generating set operation for changed field
                $atomics['$set'][(!empty($container) ? $container . '.' : '') . $field] = $value;
            }
        }

        return $atomics;
    }

    /**
     * {@inheritdoc}
     */
    public function commitUpdates()
    {
        $this->changes = [];

        foreach ($this->getFields(false) as $field => $value) {
            if ($value instanceof CompositableInterface) {
                $value->commitUpdates();
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $includeID Set to false to exclude _id from packed fields.
     */
    public function packValue(bool $includeID = true)
    {
        $values = parent::packValue();

        if (!$includeID) {
            unset($values['_id']);
        }

        return $values;
    }

    /**
     * Since most of ODM documents might contain ObjectIDs and other fields we will try to normalize
     * them into string values.
     *
     * @return array
     */
    public function publicFields(): array
    {
        $public = parent::publicFields();

        array_walk_recursive($public, function (&$value) {
            if ($value instanceof ObjectID) {
                $value = (string)$value;
            }
        });

        return $public;
    }

    /**
     * Cloning will be called when object will be embedded into another document.
     */
    public function __clone()
    {
        //De-serialize document in order to ensure that all compositions are recreated
        $this->stateValue($this->packValue());

        //Since document embedded as one piece let's ensure that it is solid
        $this->solidState = true;
        $this->changes = [];
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'fields'  => $this->getFields(),
            'atomics' => $this->hasUpdates() ? $this->buildAtomics() : [],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see CompositionDefinition
     */
    protected function getMutator(string $field, string $mutator)
    {
        /**
         * Every document composition is valid accessor but defined a bit differently.
         */
        if (isset($this->documentSchema[self::SH_COMPOSITIONS][$field])) {
            return $this->documentSchema[self::SH_COMPOSITIONS][$field];
        }

        return parent::getMutator($field, $mutator);
    }

    /**
     * {@inheritdoc}
     */
    protected function isNullable(string $field): bool
    {
        if (array_key_exists($field, $this->documentSchema[self::SH_DEFAULTS])) {
            //Only fields with default null value can be nullable
            return is_null($this->documentSchema[self::SH_DEFAULTS][$field]);
        }

        //Values unknown to schema always nullable
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * DocumentEntity will pass ODM instance as part of accessor context.
     *
     * @see CompositionDefinition
     */
    protected function createAccessor(
        $accessor,
        string $name,
        $value,
        array $context = []
    ): AccessorInterface {
        if (is_array($accessor)) {
            //We are working with definition of composition.
            switch ($accessor[0]) {
                case self::ONE:
                    //Singular embedded document
                    return $this->odm->make($accessor[1], $value, false);
                case self::MANY:
                    return new DocumentCompositor($accessor[1], $value, $this->odm);
            }

            throw new AccessorException("Invalid accessor definition for field '{$name}'");
        }

        //Field as a context
        return parent::createAccessor($accessor, $name, $value, $context + ['odm' => $this->odm]);
    }

    /**
     * {@inheritdoc}
     */
    protected function iocContainer()
    {
        if ($this->odm instanceof Component) {
            //Forwarding IoC scope to parent ODM instance
            return $this->odm->iocContainer();
        }

        return parent::iocContainer();
    }

    /**
     * @param string $name
     */
    private function registerChange(string $name)
    {
        $original = $this->getField($name, null, false);

        if (!array_key_exists($name, $this->changes)) {
            //Let's keep track of how field looked before first change
            $this->changes[$name] = $original instanceof AccessorInterface
                ? $original->packValue()
                : $original;
        }
    }
}