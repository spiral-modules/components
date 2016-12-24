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
use Spiral\Models\SchematicEntity;
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
    use SaturateTrait;

    /**
     * Set of schema sections needed to describe entity behaviour.
     */
    const SH_INSTANTIATION = 0;
    const SH_DEFAULTS      = 1;
    const SH_AGGREGATIONS  = 6;
    const SH_COMPOSITIONS  = 7;

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
     * Parent ODM instance, responsible for aggregations and lazy loading operations.
     *
     * @invisible
     * @var ODMInterface
     */
    protected $odm;

    /**
     * Document behaviour schema.
     *
     * @var array
     */
    private $schema = [];

    /**
     * {@inheritdoc}
     *
     * @param ODMInterface $odm To lazy create nested document ang aggregations.
     *
     * @throws ScopeException When no ODM instance can be resolved.
     */
    public function __construct($fields, array $schema = null, ODMInterface $odm = null)
    {
        //We can use global container as fallback if no default values were provided
        $this->odm = $this->saturate($odm, ODMInterface::class);

        //Use supplied schema or fetch one from ODM
        $this->schema = !empty($schema) ? $schema : $this->odm->schema(
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

    public function withODM()
    {
        //?????
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
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        // TODO: Implement flushUpdates() method.
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
    protected function iocContainer()
    {
        if (!empty($this->odm) || $this->odm instanceof Component) {
            //Forwarding IoC scope to parent ODM instance
            return $this->odm->iocContainer();
        }

        return parent::iocContainer();
    }
}