<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

use Spiral\Core\Component;
use Spiral\Core\Exceptions\ScopeException;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\AccessorInterface;
use Spiral\Models\SchematicEntity;
use Spiral\Models\Traits\SolidStateTrait;
use Spiral\ODM\Entities\DocumentInstantiator;

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
    use SaturateTrait, SolidStateTrait;

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
    private $schema = [];

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
    public function __construct($fields = [], array $schema = null, ODMInterface $odm = null)
    {
        //We can use global container as fallback if no default values were provided
        $this->odm = $this->saturate($odm, ODMInterface::class);

        //Use supplied schema or fetch one from ODM
        $this->schema = !empty($schema) ? $schema : $this->odm->define(
            static::class,
            ODMInterface::D_SCHEMA
        );

        $fields = is_array($fields) ? $fields : [];
        if (!empty($this->schema[self::SH_DEFAULTS])) {
            //Merging with default values
            $fields = array_replace_recursive($this->schema[self::SH_DEFAULTS], $fields);
        }

        parent::__construct($fields, $this->schema);
    }

    public function getField(string $name, $default = null, bool $filter = true)
    {
        return parent::getField($name, $default, $filter);
    }

    public function setField(string $name, $value, bool $filter = true)
    {
        return parent::setField($name, $value, $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function hasUpdates(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function buildAtomics(string $container = ''): array
    {
        // TODO: Implement buildAtomics() method.
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        $this->updates = $this->atomics = [];

        foreach ($this->getFields(false) as $value) {
            if ($value instanceof CompositableInterface) {
                $value->flushUpdates();
            }
        }
    }

    /**
     * Cloning will be called when object will be embedded into another document.
     */
    public function __clone()
    {
        //Since document embedded as one piece let's ensure that it is solid
        $this->solidState = true;

        //De-serialize document in order to ensure that all compositions are recreated
        $this->setValue($this->packValue());
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
     */
    protected function getMutator(string $field, string $mutator)
    {
        /**
         * Every document composition is valid accessor but defined a bit differently.
         */
        if (isset($this->schema[self::SH_COMPOSITIONS][$field])) {
            return $this->schema[self::SH_COMPOSITIONS][$field];
        }

        return parent::getMutator($field, $mutator);
    }

    /**
     * {@inheritdoc}
     */
    protected function isNullable(string $field): bool
    {
        if (array_key_exists($field, $this->schema[self::SH_DEFAULTS])) {
            //Only fields with default null value can be nullable
            return is_null($this->schema[self::SH_DEFAULTS][$field]);
        }

        //You can redefine custom logic to indicate what fields are nullable
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * DocumentEntity will pass ODM instance as part of accessor context.
     */
    protected function createAccessor(
        $accessor,
        string $field,
        $value,
        array $context = []
    ): AccessorInterface {
        if (is_array($accessor)) {
            /**
             * We are working with definition of composition.
             */
            switch ($accessor[0]) {
                case self::ONE:
                    //Singular embedded document
                    return $this->odm->instantiate($accessor[1], $value);
                case self::MANY:
                    return 'compositor';
            }
        }

        //Field as a context
        return parent::createAccessor($accessor, $field, $value, $context + ['odm' => $this->odm]);
    }

    /**
     * {@inheritdoc}
     */
    protected function iocContainer()
    {
        if (!empty($this->odm) || $this->odm instanceof Component) {
            //Forwarding IoC scope to parent ODM instance
            return $this->odm->iocContainer();
        }

        return parent::iocContainer();
    }
}