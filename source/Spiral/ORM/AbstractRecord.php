<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Models\AccessorInterface;
use Spiral\Models\SchematicEntity;
use Spiral\Models\Traits\SolidableTrait;
use Spiral\ORM\Entities\RelationBucket;

/**
 * Provides data and relation access functionality.
 */
abstract class AbstractRecord extends SchematicEntity
{
    use SolidableTrait;

    /**
     * Set of schema sections needed to describe entity behaviour.
     */
    const SH_PRIMARY_KEY = 0;
    const SH_DEFAULTS    = 1;
    const SH_RELATIONS   = 6;

    /**
     * Record behaviour definition.
     *
     * @var array
     */
    private $recordSchema = [];

    /**
     * Record field updates (changed values). This array contain set of initial property values if
     * any of them changed.
     *
     * @var array
     */
    private $changes = [];

    /**
     * AssociatedRelation bucket. Manages declared record relations.
     *
     * @var RelationBucket
     */
    protected $relations;

    /**
     * Parent ORM instance, responsible for relation initialization and lazy loading operations.
     *
     * @invisible
     * @var ORMInterface
     */
    protected $orm;

    /**
     * @param ORMInterface   $orm
     * @param array          $data
     * @param RelationBucket $relations
     */
    public function __construct(
        ORMInterface $orm,
        array $data = [],
        RelationBucket $relations
    ) {
        $this->orm = $orm;
        $this->recordSchema = $this->orm->define(static::class, ORMInterface::R_SCHEMA);

        $this->relations = $relations;
        $this->relations->extractRelations($data);

        //Populating default fields
        parent::__construct($data + $this->recordSchema[self::SH_DEFAULTS], $this->recordSchema);
    }

    /**
     * Get value of primary of model. Make sure to call isLoaded first!
     *
     * @return int|string|null
     */
    public function primaryKey()
    {
        return $this->getField($this->primaryColumn(), null);
    }

    /**
     * {@inheritdoc}
     *
     * @throws RelationException
     */
    public function getField(string $name, $default = null, bool $filter = true)
    {
        if ($this->relations->has($name)) {
            return $this->relations->getRelated($name);
        }

        $this->assertField($name);

        return parent::getField($name, $default, $filter);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $registerChanges Track field changes.
     *
     * @throws RelationException
     */
    public function setField(
        string $name,
        $value,
        bool $filter = true,
        bool $registerChanges = true
    ) {
        if ($this->relations->has($name)) {
            //Would not work with relations which do not represent singular entities
            $this->relations->setRelated($name, $value);

            return;
        }

        $this->assertField($name);
        if ($registerChanges) {
            $this->registerChange($name);
        }

        parent::setField($name, $value, $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function hasField(string $name): bool
    {
        if ($this->relations->has($name)) {
            return $this->relations->hasRelated($name);
        }

        return parent::hasField($name);
    }

    /**
     * {@inheritdoc}
     *
     * @throws FieldException
     * @throws RelationException
     */
    public function __unset($offset)
    {
        if ($this->relations->has($offset)) {
            //Flush associated relation value if possible
            $this->relations->flushRelated($offset);

            return;
        }

        if (!$this->isNullable($offset)) {
            throw new FieldException("Unable to unset not nullable field '{$offset}'");
        }

        $this->setField($offset, null, false);
    }

    /**
     * {@inheritdoc}
     *
     * Method does not check updates in nested relation, but only in primary record.
     *
     * @param string $field Check once specific field changes.
     */
    public function hasChanges(string $field = null): bool
    {
        //Check updates for specific field
        if (!empty($field)) {
            if (array_key_exists($field, $this->changes)) {
                return true;
            }

            //Do not force accessor creation
            $value = $this->getField($field, null, false);
            if ($value instanceof RecordAccessorInterface && $value->hasUpdates()) {
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
            if ($value instanceof RecordAccessorInterface && $value->hasUpdates()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'database'  => $this->orm->define(static::class, ORMInterface::R_DATABASE),
            'table'     => $this->orm->define(static::class, ORMInterface::R_TABLE),
            'fields'    => $this->getFields(),
            'relations' => $this->relations
        ];
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
        //Giving ORM as context
        return parent::createAccessor($accessor, $name, $value, $context + ['orm' => $this->orm]);
    }

    /**
     * {@inheritdoc}
     */
    protected function iocContainer()
    {
        if ($this->orm instanceof Component) {
            //Forwarding IoC scope to parent ORM instance
            return $this->orm->iocContainer();
        }

        return parent::iocContainer();
    }

    /**
     * Name of column used as primary key.
     *
     * @return string
     */
    protected function primaryColumn(): string
    {
        return $this->recordSchema[self::SH_PRIMARY_KEY];
    }

    /**
     * Create set of fields to be sent to UPDATE statement.
     *
     * @param bool $skipPrimary Skip primary key
     *
     * @return array
     */
    protected function packChanges(bool $skipPrimary = false): array
    {
        if (!$this->hasChanges() && !$this->isSolid()) {
            return [];
        }

        if ($this->isSolid()) {
            //Solid record always updated as one big solid
            $updates = $this->packValue();
        } else {
            //Updating each field individually
            $updates = [];
            foreach ($this->getFields(false) as $field => $value) {
                //Handled by sub-accessor
                if ($value instanceof RecordAccessorInterface) {
                    if ($value->hasUpdates()) {
                        $updates[$field] = $value->compileUpdates($field);
                        continue;
                    }

                    $value = $value->packValue();
                }

                //Field change registered
                if (array_key_exists($field, $this->changes)) {
                    $updates[$field] = $value;
                }
            }
        }

        if ($skipPrimary) {
            unset($updates[$this->primaryColumn()]);
        }

        return $updates;
    }

    /**
     * Indicate that all updates done, reset dirty state.
     */
    protected function flushChanges()
    {
        $this->changes = [];

        foreach ($this->getFields(false) as $field => $value) {
            if ($value instanceof RecordAccessorInterface) {
                $value->flushUpdates();
            }
        }
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

    /**
     * @param string $name
     *
     * @throws FieldException
     */
    private function assertField(string $name)
    {
        if (!$this->hasField($name)) {
            throw new FieldException(sprintf(
                "No such property '%s' in '%s', check schema being relevant",
                $name,
                get_called_class()
            ));
        }
    }
}