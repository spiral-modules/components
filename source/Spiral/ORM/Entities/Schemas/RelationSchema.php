<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\ORM\Entities\SchemaBuilder;
use Spiral\ORM\Exceptions\ModelSchemaException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Model;
use Spiral\ORM\ORM;
use Spiral\ORM\RelationSchemaInterface;

/**
 * Generic (abstract) implementation of relation schema. Used for basic ORM relations.
 */
abstract class RelationSchema implements RelationSchemaInterface
{
    /**
     * Must contain relation type, this constant is required to fetch outer model(s) class name from
     * relation definition.
     */
    const RELATION_TYPE = null;

    /**
     * Some relations may declare that polymorphic must be used instead, polymorphic relation type
     * must be stated here.
     */
    const EQUIVALENT_RELATION = null;

    /**
     * Size of string column dedicated to store outer role name. Used in polymorphic relations.
     * Even simple relations might include morph key (usually such relations created via inversion
     * of polymorphic relation).
     *
     * @see ModelSchema::getRole()
     */
    const MORPH_COLUMN_SIZE = 32;

    /**
     * @var string
     */
    private $name = '';

    /**
     * Name of target model or interface. Must be fetched from definition.
     *
     * @var string
     */
    private $target = '';

    /**
     * Definition specified by model schema or another definition.
     *
     * @var array
     */
    protected $definition = [];

    /**
     * Most of relations provides ability to specify many different configuration options, such
     * as key names, pivot table schemas, foreign key request, ability to be nullabe and etc.
     *
     * To simple schema definition in real projects we can fill some of this values automatically
     * based on some "environment" values such as parent/outer model table, role name, primary key
     * and etc.
     *
     * Example:
     * ActiveRecord::INNER_KEY => '{outer:role}_{outer:primaryKey}'
     *
     * Result:
     * Outer Model is User with primary key "id" => "user_id"
     *
     * @invisible
     * @var array
     */
    protected $defaultDefinition = [];

    /**
     * @invisible
     * @var SchemaBuilder
     */
    protected $builder = null;

    /**
     * @invisible
     * @var ModelSchema
     */
    protected $model = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        SchemaBuilder $builder,
        ModelSchema $model,
        $name,
        array $definition
    ) {
        $this->builder = $builder;
        $this->model = $model;

        $this->name = $name;

        //We can use definition type to fetch target outer model or interface
        $this->target = $definition[static::RELATION_TYPE];

        $this->definition = $definition;
        if ($this->hasEquivalent()) {
            return;
        }

        if (!class_exists($this->target) && !interface_exists($this->target)) {
            throw new RelationSchemaException(
                "Unable to build relation from '{$this->model}' to undefined target '{$this->target}'."
            );
        }

        $this->clarifyDefinition();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return static::RELATION_TYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function hasEquivalent()
    {
        if (!static::EQUIVALENT_RELATION) {
            //No equivalents
            return false;
        }

        //Let's switch to polymorphic relation
        return (new \ReflectionClass($this->target))->isInterface();
    }

    /**
     * {@inheritdoc}
     */
    public function createEquivalent()
    {
        if (!$this->hasEquivalent()) {
            throw new RelationSchemaException(
                "Relation '{$this->model}'.'{$this}' does not have equivalents."
            );
        }
        //Let's convert to polymorphic
        $definition = [
                static::EQUIVALENT_RELATION => $this->target
            ] + $this->definition;

        unset($definition[static::RELATION_TYPE]);

        //Usually when relation declared as polymorphic
        return $this->builder->relationSchema($this->model, $this->name, $definition);
    }

    /**
     * {@inheritdoc}
     */
    public function isInversable()
    {
        return isset($this->definition[Model::INVERSE]);
    }

    /**
     * Some relations may not have any associated record, which means that inner/outer key must
     * support nullable values. Models must allow setting fields like that to null.
     *
     * @return bool
     */
    public function isNullable()
    {
        if (array_key_exists(Model::NULLABLE, $this->definition)) {
            return $this->definition[Model::NULLABLE];
        }

        return false;
    }

    /**
     * Indication that relation has declared morph key. This means that request to outer data must
     * not only include inner key, but it must state value of morph key (usually model role name).
     *
     * Most of relations like that created automatically as inversion of polymorphic relation to
     * it's related model(s).
     *
     * @return bool
     */
    public function hasMorphKey()
    {
        return !empty($this->definition[Model::MORPH_KEY]);
    }

    /**
     * Indication that relation allowed to create indexes in outer or inner tables.
     *
     * @return bool
     */
    public function isIndexed()
    {
        return !empty($this->definition[Model::CREATE_INDEXES]);
    }

    /**
     * Check if relation requests foreign key constraints to be created.
     *
     * @return bool
     */
    public function isConstrained()
    {
        if (!$this->isSameDatabase()) {
            //Unable to create constraint when relation points to another database
            return false;
        }

        if (array_key_exists(Model::CONSTRAINT, $this->definition)) {
            return $this->definition[Model::CONSTRAINT];
        }

        return false;
    }

    /**
     * Some relations allows user to specify what type of delete/update behaviour must be applied to
     * created foreign keys.
     *
     * @return string|null
     */
    public function getConstraintAction()
    {
        if (array_key_exists(Model::CONSTRAINT_ACTION, $this->definition)) {
            return $this->definition[Model::CONSTRAINT_ACTION];
        }

        return null;
    }

    /**
     * Check if parent and related models belongs to same database, it will allow ORM to use joins
     * to preload or filter by related data.
     *
     * @return bool
     * @throws SchemaException
     */
    public function isSameDatabase()
    {
        if ($this->builder->hasModel($this->target)) {
            //Usually it tells us that relation relates to many different models (polymorphic)
            //We can't clearly say
            return false;
        }

        //Databases must be the same
        return $this->model->getDatabase() == $this->outerModel()->getDatabase();
    }

    /**
     * Declared inner key. Must return null if no key defined or required.
     *
     * @return null|string
     */
    public function getInnerKey()
    {
        if (isset($this->definition[Model::INNER_KEY])) {
            return $this->definition[Model::INNER_KEY];
        }

        return null;
    }

    /**
     * Declared outer key. Must return null if no key defined or required.
     *
     * @return null|string
     */
    public function getOuterKey()
    {
        if (isset($this->definition[Model::OUTER_KEY])) {
            return $this->definition[Model::OUTER_KEY];
        }

        return null;
    }

    /**
     * Calculates abstract type of inner key. Must return null if no key defined or required.
     * Primary types will be converted to appropriate sized integers.
     *
     * @return null|string
     * @throws SchemaException
     */
    public function getInnerKeyType()
    {
        if (empty($innerKey = $this->getInnerKey())) {
            return null;
        }

        return $this->resolveAbstract(
            $this->model->tableSchema()->column($innerKey)
        );
    }

    /**
     * Calculates abstract type of outer key. Must return null if no key defined or required.
     * Primary types will be converted to appropriate sized integers.
     *
     * @return null|string
     * @throws SchemaException
     */
    public function getOuterKeyType()
    {
        if (empty($outerKey = $this->getOuterKey())) {
            return null;
        }

        return $this->resolveAbstract(
            $this->outerModel()->tableSchema()->column($outerKey)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeSchema()
    {
        return [
            ORM::R_TYPE       => static::RELATION_TYPE,
            ORM::R_DEFINITION => $this->normalizeDefinition()
        ];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

    //    /**
    //     * Simplified method to cast column type and options by provided definition.
    //     *
    //     * @param AbstractColumn $column
    //     * @param string         $definition
    //     */
    //    protected function castColumn(AbstractColumn $column, $definition)
    //    {
    //        $validType = preg_match(
    //            '/(?P<type>[a-z]+)(?: *\((?P<options>[^\)]+)\))?(?: *, *(?P<nullable>null(?:able)?))?/i',
    //            $definition,
    //            $matches
    //        );
    //
    //        //Parsing definition
    //        if (!$validType) {
    //            throw new ORMException(
    //                "Unable to parse definition of pivot column {$this->getName()}.'{$column->getName()}'."
    //            );
    //        }
    //
    //        $column->nullable(false);
    //        if (!empty($matches['nullable'])) {
    //            //No need to force NOT NULL as this is default column state
    //            $column->nullable(true);
    //        }
    //
    //        $type = $matches['type'];
    //
    //        $options = [];
    //        if (!empty($matches['options'])) {
    //            $options = array_map('trim', explode(',', $matches['options']));
    //        }
    //
    //        call_user_func_array([$column, $type], $options);
    //    }

    /**
     * Normalize schema definition into light cachable form.
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
            $definition[Model::CREATE_INDEXES]
        );

        return $definition;
    }

    /**
     * Will specify missing fields in relation definition using default definition options. Such
     * options are dynamic and populated based on values fetched from related models.
     */
    protected function clarifyDefinition()
    {
        foreach ($this->defaultDefinition as $property => $pattern) {
            if (isset($this->definition[$property])) {
                //Specified by user
                continue;
            }

            if (!is_string($pattern)) {
                //Some options are actually array of options
                $this->definition[$property] = $pattern;
                continue;
            }

            //Let's create option value using default proposer values
            $this->definition[$property] = \Spiral\interpolate(
                $pattern,
                $this->proposedDefinitions()
            );
        }
    }

    /**
     * Create set of options to specify missing relation definition fields.
     *
     * @return array
     */
    protected function proposedDefinitions()
    {
        $options = [
            //Relation name
            'name'             => $this->name,
            //Relation name in plural form
            'name:plural'      => Inflector::pluralize($this->name),
            //Relation name in singular form
            'name:singular'    => Inflector::singularize($this->name),
            //Parent model role name
            'model:role'       => $this->model->getRole(),
            //Parent model table name
            'model:table'      => $this->model->getTable(),
            //Parent model primary key
            'model:primaryKey' => $this->model->getPrimaryKey()
        ];

        //Some options may use values declared in other definition fields
        $proposed = [
            Model::OUTER_KEY   => 'outerKey',
            Model::INNER_KEY   => 'innerKey',
            Model::PIVOT_TABLE => 'pivotTable'
        ];

        foreach ($proposed as $property => $alias) {
            if (isset($this->definition[$property])) {
                //Let's create some default options based on user specified values
                $options['definition:' . $alias] = $this->definition[$property];
            }
        }

        if ($this->builder->hasModel($this->target)) {
            $options = $options + [
                    //Outer role name
                    'outer:role'       => $this->outerModel()->getRole(),
                    //Outer model table
                    'outer:table'      => $this->outerModel()->getTable(),
                    //Outer model primary key
                    'outer:primaryKey' => $this->outerModel()->getPrimaryKey()
                ];
        }

        return $options;
    }

    /**
     * Get ModelSchema to be associated with, method must throw an exception if outer model not
     * found.
     *
     * @return ModelSchema
     * @throws RelationSchemaException
     * @throws SchemaException
     * @throws ModelSchemaException
     */
    protected function outerModel()
    {
        if (!$this->builder->hasModel($this->target)) {
            throw new RelationSchemaException(
                "Undefined outer model '{$this->target}' in relation '{$this->model}'.'{$this}'."
            );
        }

        return $this->builder->model($this->target);
    }

    /**
     * Resolve correct abstract type to represent inner or outer key. Primary types will be converted
     * to appropriate sized integers.
     *
     * @param AbstractColumn $column
     * @return string
     */
    private function resolveAbstract(AbstractColumn $column)
    {
        switch ($column->abstractType()) {
            case 'bigPrimary':
                return 'bigInteger';
            case 'primary':
                return 'integer';
            default:
                //Not primary key
                return $column->abstractType();
        }
    }
}