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
use Spiral\Database\Entities\Schemas\AbstractIndex;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ORM\Entities\SchemaBuilder;
use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Exceptions\ModelSchemaException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Model;
use Spiral\ORM\ModelAccessorInterface;
use Spiral\ORM\RelationSchemaInterface;

/**
 * Performs analysis, schema building and table declaration for one specific Model class.
 *
 * You have to call
 */
class ModelSchema extends ReflectionEntity
{
    /**
     * Required to validly merge parent and children attributes.
     */
    const BASE_CLASS = Model::class;

    /**
     * Every ORM Model must have associated database table, table will be used to read column names,
     * default values and write declared model changes.
     *
     * @var AbstractTable
     */
    private $tableSchema = null;

    /**
     * Declared and requested model relationships.
     *
     * @var RelationSchemaInterface[]
     */
    protected $relations = [];

    /**
     * @invisible
     * @var SchemaBuilder
     */
    protected $builder = null;

    /**
     * @param SchemaBuilder $builder Parent ORM schema (all other documents).
     * @param string        $class   Class name.
     * @throws \ReflectionException
     * @throws DefinitionException
     * @throws ModelSchemaException
     */
    public function __construct(SchemaBuilder $builder, $class)
    {
        parent::__construct($class);

        $this->builder = $builder;

        //Associated table
        $this->tableSchema = $this->builder->declareTable(
            $this->getDatabase(),
            $this->getTable()
        );

        /**
         * Use model schema (property) to declare table indexes, columns and default values.
         * No relations has to be declared at this point.
         */
        $this->castSchema();
    }

    /**
     * @return AbstractTable
     */
    public function tableSchema()
    {
        return $this->tableSchema;
    }

    /**
     * Returns true if Model states that related table can be altered by ORM. To allow schema altering
     * set model constant ACTIVE_SCHEMA to true.
     *
     * @see Model::ACTIVE_SCHEMA
     * @return bool
     */
    public function isActive()
    {
        return !empty($this->getConstant('ACTIVE_SCHEMA'));
    }

    /**
     * Model role named used widely in relations to generate inner and outer keys, define related
     * class and table in morphed relations and etc. You can defined your own role name by defining
     * model constant MODEL_ROLE.
     *
     * Example:
     * Model: Models\Post with primary key "id"
     * Relation: HAS_ONE
     * Outer key: post_id
     *
     * @return string
     */
    public function getRole()
    {
        if ($this->hasConstant('MODEL_ROLE') && !empty($this->getConstant('MODEL_ROLE'))) {
            return $this->getConstant('MODEL_ROLE');
        }

        return lcfirst($this->getName());
    }

    /**
     * Source table. In cases where table name was not specified, ModelSchema will generate need value
     * using class name and Doctrine inflector.
     *
     * @see Model::$table
     * @return mixed
     */
    public function getTable()
    {
        if (empty($table = $this->property('table'))) {
            //We can guess table name
            $table = Inflector::tableize($this->getShortName());

            //Table names are plural by default
            return Inflector::pluralize($table);
        }

        return $table;
    }

    /**
     * Get database where model data should be stored in. Database alias must be resolved.
     *
     * @see Model::$database
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->builder->resolveDatabase($this->property('database'));
    }

    /**
     * SourceID must fully describe model source table and database in context of application.
     *
     * @return string
     */
    public function getSourceID()
    {
        return $this->getDatabase() . '.' . $this->getTable();
    }

    /**
     * Name of first primary key (usually sequence). Might return null for models with no primary key.
     *
     * @return string|null
     */
    public function getPrimaryKey()
    {
        if (empty($this->tableSchema->getPrimaryKeys())) {
            return null;
        }

        //Spiral ORM can work only with singular primary keys for now... for now.
        return array_slice($this->tableSchema->getPrimaryKeys(), 0, 1)[0];
    }

    /**
     * Get declared indexes. This may not be the same set of indexes as in associated table schema,
     * use ModelSchema->tableSchema()->getIndexes() method to get real table indexes.
     *
     * @see Model::$indexes
     * @see tableSchema()
     * @return array
     */
    public function getIndexes()
    {
        return $this->property('indexes', true);
    }

    /**
     * {@inheritdoc}
     */
    public function getFields()
    {
        $result = [];
        foreach ($this->tableSchema->getColumns() as $column) {
            //Yep, so simple
            $result[$column->getName()] = $column->phpType();
        }

        return $result;
    }

    /**
     * Get column names associated with their default values. Default values will be fetched from
     * values declared by model and values declared in associated table schema. Every default value
     * will be normalized in a cachable form (no objects allowed here).
     *
     * @return array
     */
    public function getDefaults()
    {
        //We have to reiterate columns as schema can be altered while relation creation,
        //plus we always have to keep original columns order (this is very important)
        $defaults = [];
        $modelDefaults = $this->property('defaults', true);

        //We must pass all default values thought set of setters and accessor to ensure their value
        $setters = $this->getSetters();
        $accessors = $this->getAccessors();

        foreach ($this->tableSchema->getColumns() as $column) {
            //Let's use default value fetched from column first
            $default = $this->exportDefault($column);

            if (isset($modelDefaults[$column->getName()])) {
                //Let's use value declared in model schema
                $default = $modelDefaults[$column->getName()];
            }

            if (isset($accessors[$column->getName()])) {
                $accessor = $accessors[$column->getName()];
                $accessor = new $accessor($default, null);
                if ($accessor instanceof ModelAccessorInterface) {
                    $default = $accessor->defaultValue($this->tableSchema->driver());
                }
            }

            if (isset($setters[$column->getName()])) {
                try {
                    //Applying filter to default value
                    $default = call_user_func($setters[$column->getName()], $default);
                } catch (\ErrorException $exception) {
                    $default = null;
                }
            }

            $defaults[$column->getName()] = $default;
        }

        return $defaults;
    }

    /**
     * Model will utilize it's schema definition to create set of relations to other models and
     * entities (for example ODM).
     *
     * @throws SchemaException
     * @throws RelationSchemaException
     */
    public function castRelations()
    {
        foreach ($this->property('schema', true) as $name => $definition) {
            if (is_scalar($definition)) {
                //Column definition or something else
                continue;
            }

            if (!$this->hasRelation($name)) {
                $this->addRelation($name, $definition);
            }
        }
    }

    /**
     * Check if ModelSchema already have declared relation by it's name.
     *
     * @param string $name
     * @return bool
     */
    public function hasRelation($name)
    {
        return isset($this->relations[$name]);
    }

    /**
     * Declare new model relation by it's name and definition. Only unique relations can be added.
     *
     * @see SchemaBuilder::relationSchema()
     * @param string $name
     * @param array  $definition
     * @throws ModelSchemaException
     */
    public function addRelation($name, array $definition)
    {
        if (isset($this->relations[$name])) {
            throw  new ModelSchemaException(
                "Unable to create relation '{$this}'.'{$name}', relation already exists."
            );
        }

        $relation = $this->builder->relationSchema($this, $name, $definition);

        //Initiating required columns, foreign keys and indexes
        $relation->buildSchema();

        $this->relations[$name] = $relation;
    }

    /**
     * Get all declared or requested model relation schemas.
     *
     * @return RelationSchemaInterface[]
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * {@inheritdoc}
     *
     * Schema can generate accessors and filters based on field type.
     */
    public function getMutators()
    {
        $mutators = parent::getMutators();

        //Trying to resolve mutators based on field type
        foreach ($this->getFields() as $field => $type) {
            //Resolved filters
            $resolved = [];

            if (
                is_array($type)
                && is_scalar($type[0])
                && $filter = $this->builder->getMutators('array::' . $type[0])
            ) {
                //Mutator associated to array with specified type
                $resolved += $filter;
            } elseif (is_array($type) && $filter = $this->builder->getMutators('array')) {
                //Default array mutator
                $resolved += $filter;
            } elseif (!is_array($type) && $filter = $this->builder->getMutators($type)) {
                //Mutator associated with type directly
                $resolved += $filter;
            }

            //Merging mutators and default mutators
            foreach ($resolved as $mutator => $filter) {
                if (!array_key_exists($field, $mutators[$mutator])) {
                    $mutators[$mutator][$field] = $filter;
                }
            }
        }

        foreach ($mutators as $mutator => &$filters) {
            foreach ($filters as $field => $filter) {
                //Some mutators may be described using aliases (for shortness)
                $filters[$field] = $this->builder->mutatorAlias($filter);
            }
            unset($filters);
        }

        return $mutators;
    }

    /**
     * Method utilizes value of model schema property to generate table columns. Property "indexes"
     * going to feed table indexes.
     *
     * @see Model::$schema
     * @throws DefinitionException
     * @throws \Spiral\Database\Exceptions\SchemaException
     */
    protected function castSchema()
    {
        //Default values fetched from model, system will try to use this values as default
        //values for associated table column
        $defaults = $this->property('defaults', true);

        foreach ($this->property('schema', true) as $name => $definition) {
            if (is_array($definition)) {
                //Relation or something else
                continue;
            }

            //Let's cast table column using it's name, declared definition and default value (if any)
            $this->castColumn(
                $this->tableSchema->column($name),
                $definition,
                isset($defaults[$name]) ? $defaults[$name] : null
            );
        }

        //Casting declared model indexes
        foreach ($this->getIndexes() as $definition) {
            $this->castIndex($definition);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function parentSchema()
    {
        if (!$this->builder->hasModel($this->getParentClass()->getName())) {
            return null;
        }

        return $this->builder->model($this->getParentClass()->getName());
    }

    /**
     * Cast (specify) column schema based on provided column definition and default value.
     * Spiral will force default values (internally) for every NOT NULL column except primary keys!
     *
     * Column definition are compatible with database Migrations and AbstractColumn types.
     *
     * Column definition examples (by default all columns has flag NOT NULL):
     * protected $schema = [
     *      'id'           => 'primary',
     *      'name'         => 'string',                          //Default length is 255 characters.
     *      'email'        => 'string(255), nullable',           //Can be NULL
     *      'status'       => 'enum(active, pending, disabled)', //Enum values, trimmed
     *      'balance'      => 'decimal(10, 2)',
     *      'message'      => 'text, null',                      //Alias for nullable
     *      'time_expired' => 'timestamp'
     * ];
     *
     * @see AbstractColumn
     * @param AbstractColumn $column
     * @param string         $definition
     * @param mixed          $default Default value declared by model schema.
     * @return mixed
     * @throws DefinitionException
     * @throws \Spiral\Database\Exceptions\SchemaException
     */
    private function castColumn(AbstractColumn $column, $definition, $default = null)
    {
        //Expression used to declare column type, easy to read
        $pattern = '/(?P<type>[a-z]+)(?: *\((?P<options>[^\)]+)\))?(?: *, *(?P<nullable>null(?:able)?))?/i';

        if (!preg_match($pattern, $definition, $type)) {
            throw new DefinitionException(
                "Invalid column type definition in '{$this}'.'{$column->getName()}'."
            );
        }

        if (!empty($type['options'])) {
            //Exporting and trimming
            $type['options'] = array_map('trim', explode(',', $type['options']));
        }

        //We are forcing every column to be NOT NULL by default, DEFAULT value should fix potential
        //problems, nullable flag must be applied before type was set (some types do not want
        //null values to be allowed)
        $column->nullable(!empty($matches['nullable']));

        //Bypassing call to AbstractColumn->__call method (or specialized column method)
        call_user_func_array(
            [$column, $type['type']],
            !empty($type['options']) ? $type['options'] : []
        );

        if (!is_null($default)) {
            //We have default value stated my model schema
            $column->defaultValue($default);
        }

        if (!$column->hasDefaultValue() && !$column->isNullable()) {
            //Ouch, columns like that can break synchronization!
            $column->defaultValue($this->castDefault($column));
        }

        return $column;
    }

    /**
     * Cast (specify) index shema in associated table based on Model index property definition. Only
     * normal or unique indexes can be casted at this moment.
     *
     * Example:
     * protected $indexes = array(
     *      [self::UNIQUE, 'email'],
     *      [self::INDEX, 'status', 'balance'],
     *      [self::INDEX, 'public_id']
     * );
     *
     * @param array $definition
     * @return AbstractIndex
     * @throws DefinitionException
     * @throws \Spiral\Database\Exceptions\SchemaException
     */
    protected function castIndex(array $definition)
    {
        //Index type (UNIQUE or INDEX)
        $type = null;

        //Columns index associated too
        $columns = [];
        foreach ($definition as $chunk) {
            if ($chunk == Model::INDEX || $chunk == Model::UNIQUE) {
                $type = $chunk;
                continue;
            }

            if (!$this->tableSchema->hasColumn($chunk)) {
                throw new DefinitionException(
                    "Model '{$this}' has index definition with undefined local column."
                );
            }

            $columns[] = $chunk;
        }

        if (empty($type)) {
            throw new DefinitionException(
                "Model '{$this}' has index definition with unspecified index type."
            );
        }

        if (empty($columns)) {
            throw new DefinitionException(
                "Model '{$this}' has index definition without any column associated to."
            );
        }

        //Casting schema
        return $this->tableSchema->index($columns)->unique($type == Model::UNIQUE);
    }

    /**
     * Cast default value based on column type. Required to prevent conflicts when not nullable
     * column added to existed table with data in.
     *
     * @param AbstractColumn $column
     * @return bool|float|int|mixed|string
     */
    private function castDefault(AbstractColumn $column)
    {
        if ($column->abstractType() == 'timestamp' || $column->abstractType() == 'datetime') {
            $driver = $this->tableSchema->driver();

            return $driver::DEFAULT_DATETIME;
        }

        switch ($column->phpType()) {
            case 'int':
                return 0;
                break;
            case 'float':
                return 0.0;
                break;
            case 'bool':
                return false;
                break;
        }

        return '';
    }

    /**
     * Export default value from column schema into scalar form (which we can store in cache).
     *
     * @param AbstractColumn $column
     * @return mixed|null
     */
    private function exportDefault(AbstractColumn $column)
    {
        if (in_array($column->getName(), $this->tableSchema->getPrimaryKeys())) {
            //Column declared as primary key, nothing to do with default values
            return null;
        }

        $defaultValue = $column->getDefaultValue();
        if ($defaultValue instanceof SQLFragmentInterface) {
            //We can't cache values like that
            return null;
        }

        return $defaultValue;
    }
}