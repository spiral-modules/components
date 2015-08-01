<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Database\Schemas\AbstractColumn;
use Spiral\ORM\Model;
use Spiral\ORM\ORM;
use Spiral\ORM\ORMException;
use Spiral\ORM\SchemaBuilder;
use Spiral\Core\Container;

abstract class RelationSchema implements RelationSchemaInterface
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = null;

    /**
     * Equivalent relationship resolved based on definition and not schema, usually polymorphic.
     */
    const EQUIVALENT_RELATION = null;

    /**
     * Size of string column dedicated to store outer role name.
     */
    const MORPH_COLUMN_SIZE = 32;

    /**
     * Parent ORM schema holds all active record schemas.
     *
     * @invisible
     * @var SchemaBuilder
     */
    protected $builder = null;

    /**
     * Associated active record schema.
     *
     * @invisible
     * @var ModelSchema
     */
    protected $model = null;

    /**
     * Relation name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Default definition parameters, will be filled if parameter skipped from definition by user.
     * Every parameter described by it's key and pattern.
     *
     * Example:
     * ActiveRecord::INNER_KEY => '{outer:roleName}_{outer:primaryKey}'
     *
     * @invisible
     * @var array
     */
    protected $defaultDefinition = [];

    /**
     * Relation definition.
     *
     * @var array
     */
    protected $definition = [];

    /**
     * Target model or interface (for polymorphic classes).
     *
     * @var string
     */
    protected $target = '';

    /**
     * New RelationSchema instance.
     *
     * @param SchemaBuilder $builder
     * @param ModelSchema   $model
     * @param string        $name
     * @param array         $definition
     */
    public function __construct(SchemaBuilder $builder, ModelSchema $model, $name, array $definition)
    {
        $this->builder = $builder;
        $this->model = $model;

        $this->name = $name;
        $this->target = $definition[static::RELATION_TYPE];

        $this->definition = $definition;
        if ($this->hasEquivalent())
        {
            return;
        }

        if (!class_exists($this->target) && !interface_exists($this->target))
        {
            throw new ORMException(
                "Unable to build relation from '{$this->model}' "
                . "to undefined target '{$this->target}'."
            );
        }

        $this->clarifyDefinition();
    }

    /**
     * Relation name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Relation type.
     *
     * @return int
     */
    public function getType()
    {
        return static::RELATION_TYPE;
    }

    /**
     * Check if relationship has equivalent based on declared definition, default behaviour will
     * select polymorphic equivalent if target declared as interface.
     *
     * @return bool
     */
    public function hasEquivalent()
    {
        if (!static::EQUIVALENT_RELATION)
        {
            return false;
        }

        return (new \ReflectionClass($this->target))->isInterface();
    }

    /**
     * Create equivalent relation.
     *
     * @return RelationSchemaInterface
     * @throws ORMException
     */
    public function createEquivalent()
    {
        $definition = [
                static::EQUIVALENT_RELATION => $this->target
            ] + $this->definition;

        unset($definition[static::RELATION_TYPE]);

        //Usually when relation declared as polymorphic
        return $this->builder->relationSchema($this->model, $this->name, $definition);
    }

    /**
     * Relation definition contains request to be reverted.
     *
     * @return bool
     */
    public function isInversable()
    {
        return isset($this->definition[Model::INVERSE]);
    }

    /**
     * Create reverted relations in outer model or models.
     *
     * @throws ORMException
     */
    abstract public function inverseRelation();

    /**
     * Mount default values to relation definition.
     */
    protected function clarifyDefinition()
    {
        foreach ($this->defaultDefinition as $property => $pattern)
        {
            if (isset($this->definition[$property]))
            {
                continue;
            }

            if (!is_string($pattern))
            {
                $this->definition[$property] = $pattern;
                continue;
            }

            $this->definition[$property] = \Spiral\interpolate($pattern, $this->definitionOptions());
        }
    }

    /**
     * Option string used to populate definition template if no user value provided.
     *
     * @return array
     */
    protected function definitionOptions()
    {
        $options = [
            'name'              => $this->name,
            'name:plural'       => Inflector::pluralize($this->name),
            'name:singular'     => Inflector::singularize($this->name),
            'record:roleName'   => $this->model->getRoleName(),
            'record:table'      => $this->model->getTable(),
            'record:primaryKey' => $this->model->getPrimaryKey(),
        ];

        $proposed = [
            Model::OUTER_KEY   => 'OUTER_KEY',
            Model::INNER_KEY   => 'INNER_KEY',
            Model::PIVOT_TABLE => 'PIVOT_TABLE'
        ];

        foreach ($proposed as $property => $alias)
        {
            if (isset($this->definition[$property]))
            {
                $options['definition:' . $alias] = $this->definition[$property];
            }
        }

        try
        {
            if ($this->outerModel())
            {
                $options = $options + [
                        'outer:roleName'   => $this->outerModel()->getRoleName(),
                        'outer:table'      => $this->outerModel()->getTable(),
                        'outer:primaryKey' => $this->outerModel()->getPrimaryKey()
                    ];
            }
        }
        catch (ORMException $exception)
        {
            //Suppressed
        }

        return $options;
    }

    /**
     * Check if relation points to model data from another database. We should not be creating
     * foreign keys in this case.
     *
     * @return bool
     */
    public function isOuterDatabase()
    {
        $outerDatabase = $this->outerModel()->getDatabase();

        return $this->model->getDatabase() != $outerDatabase;
    }

    /**
     * Get instance on ModelSchema assosicated with outer active record (presented only for non
     * polymorphic relations).
     *
     * @return null|ModelSchema
     * @throws ORMException
     */
    protected function outerModel()
    {
        if (empty($outerModel = $this->builder->modelSchema($this->target)))
        {
            throw new ORMException("Undefined outer model '{$this->target}'.");
        }

        return $outerModel;
    }

    /**
     * Many relations can be nullable (has no parent) by default, to simplify schema creation.
     *
     * @return bool
     */
    public function isNullable()
    {
        if (array_key_exists(Model::NULLABLE, $this->definition))
        {
            return $this->definition[Model::NULLABLE];
        }

        return false;
    }

    /**
     * Check if relation requests foreign key constraints to be created.
     *
     * @return bool
     */
    public function isConstrained()
    {
        if ($this->isOuterDatabase())
        {
            //Unable to create constraint when relation points to another database
            return false;
        }

        if (array_key_exists(Model::CONSTRAINT, $this->definition))
        {
            return $this->definition[Model::CONSTRAINT];
        }

        return false;
    }

    /**
     * Constraint action to be applied to created foreign key.
     *
     * @return string|null
     */
    public function getConstraintAction()
    {
        if (array_key_exists(Model::CONSTRAINT_ACTION, $this->definition))
        {
            return $this->definition[Model::CONSTRAINT_ACTION];
        }

        return null;
    }

    /**
     * Inner key name.
     *
     * @return null|string
     */
    public function getInnerKey()
    {
        if (isset($this->definition[Model::INNER_KEY]))
        {
            return $this->definition[Model::INNER_KEY];
        }

        return null;
    }

    /**
     * Abstract type needed to represent inner key (excluding primary keys).
     *
     * @return null|string
     */
    public function innerKeyType()
    {
        if (!$innerKey = $this->getInnerKey())
        {
            return null;
        }

        return $this->resolveAbstractType($this->model->tableSchema()->column($innerKey));
    }

    /**
     * Outer key name.
     *
     * @return null|string
     */
    public function getOuterKey()
    {
        if (isset($this->definition[Model::OUTER_KEY]))
        {
            return $this->definition[Model::OUTER_KEY];
        }

        return null;
    }

    /**
     * Abstract type needed to represent outer key (excluding primary keys).
     *
     * @return null|string
     */
    public function outerKeyType()
    {
        if (!$outerKey = $this->getOuterKey())
        {
            return null;
        }

        return $this->resolveAbstractType($this->outerModel()->tableSchema()->column($outerKey));
    }

    /**
     * Resolve correct abstract type to represent inner or outer key. Primary types will be converted
     * to appropriate sized integers.
     *
     * @param AbstractColumn $column
     * @return string
     */
    protected function resolveAbstractType(AbstractColumn $column)
    {
        switch ($column->abstractType())
        {
            case 'bigPrimary':
                return 'bigInteger';
            case 'primary':
                return 'integer';
            default:
                return $column->abstractType();
        }
    }

    /**
     * Simplified method to cast column type and options by provided definition.
     *
     * @param AbstractColumn $column
     * @param string         $definition
     */
    protected function castColumn(AbstractColumn $column, $definition)
    {
        $validType = preg_match(
            '/(?P<type>[a-z]+)(?: *\((?P<options>[^\)]+)\))?(?: *, *(?P<nullable>null(?:able)?))?/i',
            $definition,
            $matches
        );

        //Parsing definition
        if (!$validType)
        {
            throw new ORMException(
                "Unable to parse definition of pivot column {$this->getName()}.'{$column->getName()}'."
            );
        }

        $column->nullable(false);
        if (!empty($matches['nullable']))
        {
            //No need to force NOT NULL as this is default column state
            $column->nullable(true);
        }

        $type = $matches['type'];

        $options = [];
        if (!empty($matches['options']))
        {
            $options = array_map('trim', explode(',', $matches['options']));
        }

        call_user_func_array([$column, $type], $options);
    }

    /**
     * Create all required relation columns, indexes and constraints.
     */
    abstract public function buildSchema();

    /**
     * Normalize relation options.
     *
     * @return array
     */
    protected function normalizeDefinition()
    {
        $definition = $this->definition;

        //Unnecessary fields.
        unset(
            $definition[Model::CONSTRAINT],
            $definition[Model::CONSTRAINT_ACTION],
            $definition[Model::CREATE_PIVOT],
            $definition[Model::INVERSE],
            $definition[Model::CONSTRAINT_ACTION]
        );

        return $definition;
    }

    /**
     * Pack relation data into normalized structured to be used in cached ORM schema.
     *
     * @return array
     */
    public function normalizeSchema()
    {
        return [
            ORM::R_TYPE       => static::RELATION_TYPE,
            ORM::R_DEFINITION => $this->normalizeDefinition()
        ];
    }
}