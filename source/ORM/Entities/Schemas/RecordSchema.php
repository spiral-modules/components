<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractIndex;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Injections\FragmentInterface;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ORM\Entities\SchemaBuilder;
use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Exceptions\RecordSchemaException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\RecordAccessorInterface;
use Spiral\ORM\RecordEntity;
use Spiral\ORM\Schemas\RelationInterface;

/**
 * Performs analysis, schema building and table declaration for one specific Record class.
 *
 * You have to call
 */
class RecordSchema extends ReflectionEntity
{
    /**
     * Required to validly merge parent and children attributes.
     */
    const BASE_CLASS = RecordEntity::class;

    /**
     * Related source class.
     *
     * @var string
     */
    private $source = null;

    /**
     * Every ORM Record must have associated database table, table will be used to read column
     * names, default values and write declared record changes.
     *
     * @var AbstractTable
     */
    private $tableSchema = null;

    /**
     * Declared and requested record relationships.
     *
     * @var RelationInterface[]
     */
    protected $relations = [];

    /**
     * @invisible
     *
     * @var SchemaBuilder
     */
    protected $builder = null;

    /**
     * @param SchemaBuilder $builder Parent ORM schema (all other documents).
     * @param string        $class   Class name.
     *
     * @throws \ReflectionException
     * @throws DefinitionException
     * @throws RecordSchemaException
     */
    public function __construct(SchemaBuilder $builder, $class)
    {
        parent::__construct($class);

        $this->builder = $builder;

        //Associated table
        $this->tableSchema = $this->builder->declareTable($this->getDatabase(), $this->getTable());
    }

    /**
     * Associate source class.
     *
     * @param string $class
     */
    public function setSource($class)
    {
        $this->source = $class;
    }

    /**
     * Related source class.
     *
     * @return string|null
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return AbstractTable
     */
    public function tableSchema()
    {
        return $this->tableSchema;
    }

    /**
     * Returns true if Record states that related table can be altered by ORM. To allow schema
     * altering set record constant ACTIVE_SCHEMA to true.
     *
     * Tables associated to records with ACTIVE_SCHEMA = false counted as "passive".
     *
     * @see Record::ACTIVE_SCHEMA
     *
     * @return bool
     */
    public function isActive()
    {
        return !empty($this->getConstant('ACTIVE_SCHEMA'));
    }

    /**
     * Record role named used widely in relations to generate inner and outer keys, define related
     * class and table in morphed relations and etc. You can defined your own role name by defining
     * record constant MODEL_ROLE.
     *
     * Example:
     * Record: Records\Post with primary key "id"
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

        return lcfirst($this->getShortName());
    }

    /**
     * Source table. In cases where table name was not specified, RecordSchema will generate need
     * value using class name and Doctrine inflector.
     *
     * @see Record::$table
     *
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
     * Get database where record data should be stored in. Database alias must be resolved.
     *
     * @see Record::$database
     *
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->builder->resolveDatabase($this->property('database'));
    }

    /**
     * SourceID must fully describe record source table and database in context of application.
     *
     * @return string
     */
    public function getTableID()
    {
        return $this->getDatabase() . '.' . $this->getTable();
    }

    /**
     * Name of first primary key (usually sequence). Might return null for records with no primary
     * key.
     *
     * @return string|null
     */
    public function getPrimaryKey()
    {
        if (empty($this->tableSchema->getPrimaryKeys())) {
            return;
        }

        //Spiral ORM can work only with singular primary keys for now... for now.
        return array_slice($this->tableSchema->getPrimaryKeys(), 0, 1)[0];
    }

    /**
     * Get declared indexes. This may not be the same set of indexes as in associated table schema,
     * use RecordSchema->tableSchema()->getIndexes() method to get real table indexes.
     *
     * @see Record::$indexes
     * @see tableSchema()
     *
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
     * values declared by record and values declared in associated table schema. Every default value
     * will be normalized in a cachable form (no objects allowed here).
     *
     * @return array
     */
    public function getDefaults()
    {
        //We have to reiterate columns as schema can be altered while relation creation,
        //plus we always have to keep original columns order (this is very important)
        $defaults = [];
        $recordDefaults = $this->property('defaults', true);

        //We must pass all default values thought set of setters and accessor to ensure their value
        $setters = $this->getSetters();
        $accessors = $this->getAccessors();

        foreach ($this->tableSchema->getColumns() as $column) {

            //Let's use default value fetched from column first
            $default = $this->exportDefault($column);

            if (isset($recordDefaults[$column->getName()])) {
                //Let's use value declared in record schema
                $default = $recordDefaults[$column->getName()];
            }

            if (is_null($default) && in_array($column->getName(), $this->getNullable())) {
                //We must keep null values
                $defaults[$column->getName()] = $default;
                continue;
            }

            if (isset($accessors[$column->getName()])) {
                $accessor = $accessors[$column->getName()];
                $accessor = new $accessor($default, null);
                if ($accessor instanceof RecordAccessorInterface) {
                    $default = $accessor->defaultValue($this->tableSchema->driver());
                }
            }

            if (isset($setters[$column->getName()])) {
                try {
                    $setter = $setters[$column->getName()];

                    //Applying filter to default value
                    $default = call_user_func($setter, $default);
                } catch (\ErrorException $exception) {
                    //Ignoring
                }
            }

            $defaults[$column->getName()] = $default;
        }

        return $defaults;
    }

    /**
     * Get array of fields which can be set with null value. Record schema must allow setting this
     * values to null and bypass filters.
     *
     * @return array
     */
    public function getNullable()
    {
        $result = [];
        foreach ($this->tableSchema->getColumns() as $column) {
            if ($column->isNullable()) {
                $result[] = $column->getName();
            }
        }

        //Let's include primary keys to nullable fields
        return array_unique(array_merge($result, $this->tableSchema->getPrimaryKeys()));
    }

    /**
     * Method utilizes value of record schema property to generate table columns. Property "indexes"
     * going to feed table indexes.
     *
     * @see Record::$schema
     *
     * @throws DefinitionException
     * @throws \Spiral\Database\Exceptions\SchemaException
     */
    public function castSchema()
    {
        //Default values fetched from record, system will try to use this values as default
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

        //Casting declared record indexes
        foreach ($this->getIndexes() as $definition) {
            $this->castIndex($definition);
        }
    }

    /**
     * Record will utilize it's schema definition to create set of relations to other records and
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
     * Check if RecordSchema already have declared relation by it's name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasRelation($name)
    {
        return isset($this->relations[$name]);
    }

    /**
     * Declare new record relation by it's name and definition. Only unique relations can be added.
     *
     * @see SchemaBuilder::relationSchema()
     *
     * @param string $name
     * @param array  $definition
     *
     * @throws RecordSchemaException
     */
    public function addRelation($name, array $definition)
    {
        if (isset($this->relations[$name])) {
            throw new RecordSchemaException(
                "Unable to create relation '{$this}'.'{$name}', relation already exists."
            );
        }

        $relation = $this->builder->relationSchema($this, $name, $definition);

        //We can cast relation only if it's parent class has active schema
        if ($this->isActive() && $relation->isReasonable()) {
            //Initiating required columns, foreign keys and indexes
            $relation->buildSchema();
        }

        $this->relations[$name] = $relation;
    }

    /**
     * Get all declared or requested record relation schemas.
     *
     * @return RelationInterface[]
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
        foreach ($this->tableSchema->getColumns() as $column) {
            //Resolved filters
            $resolved = [];

            if (!empty($filter = $this->builder->getMutators($column->abstractType()))) {
                //Mutator associated with type directly
                $resolved += $filter;
            } elseif (!empty($filter = $this->builder->getMutators('php:' . $column->phpType()))) {
                //Mutator associated with php type
                $resolved += $filter;
            }

            //Merging mutators and default mutators
            foreach ($resolved as $mutator => $filter) {
                if (!array_key_exists($column->getName(), $mutators[$mutator])) {
                    $mutators[$mutator][$column->getName()] = $filter;
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
     * {@inheritdoc}
     */
    protected function parentSchema()
    {
        if (!$this->builder->hasRecord($this->getParentClass()->getName())) {
            return;
        }

        return $this->builder->record($this->getParentClass()->getName());
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
     *
     * @param AbstractColumn $column
     * @param string         $definition
     * @param mixed          $default Default value declared by record schema.
     *
     * @return mixed
     *
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
        $column->nullable(!empty($type['nullable']));

        //Bypassing call to AbstractColumn->__call method (or specialized column method)
        call_user_func_array(
            [$column, $type['type']],
            !empty($type['options']) ? $type['options'] : []
        );

        if (in_array($column->getName(), $this->tableSchema->getPrimaryKeys())) {
            //No default value can be set of primary keys
            return $column;
        }

        if (!is_null($default)) {
            //We have default value stated my record schema
            $column->defaultValue($default);
        }

        if (!$column->hasDefaultValue() && !$column->isNullable()) {
            //Ouch, columns like that can break synchronization!
            $column->defaultValue($this->castDefault($column));
        }

        return $column;
    }

    /**
     * Cast (specify) index shema in associated table based on Record index property definition.
     * Only normal or unique indexes can be casted at this moment.
     *
     * Example:
     * protected $indexes = array(
     *      [self::UNIQUE, 'email'],
     *      [self::INDEX, 'status', 'balance'],
     *      [self::INDEX, 'public_id']
     * );
     *
     * @param array $definition
     *
     * @return AbstractIndex
     *
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
            if ($chunk == RecordEntity::INDEX || $chunk == RecordEntity::UNIQUE) {
                $type = $chunk;
                continue;
            }

            if (!$this->tableSchema->hasColumn($chunk)) {
                throw new DefinitionException(
                    "Record '{$this}' has index definition with undefined local column."
                );
            }

            $columns[] = $chunk;
        }

        if (empty($type)) {
            throw new DefinitionException(
                "Record '{$this}' has index definition with unspecified index type."
            );
        }

        if (empty($columns)) {
            throw new DefinitionException(
                "Record '{$this}' has index definition without any column associated to."
            );
        }

        //Casting schema
        return $this->tableSchema->index($columns)->unique($type == RecordEntity::UNIQUE);
    }

    /**
     * Export default value from column schema into scalar form (which we can store in cache).
     *
     * @param AbstractColumn $column
     *
     * @return mixed|null
     */
    private function exportDefault(AbstractColumn $column)
    {
        if (in_array($column->getName(), $this->tableSchema->getPrimaryKeys())) {
            //Column declared as primary key, nothing to do with default values
            return;
        }

        $defaultValue = $column->getDefaultValue();
        if ($defaultValue instanceof FragmentInterface) {
            //We can't cache values like that
            return;
        }

        if (is_null($defaultValue) && !$column->isNullable()) {
            return $this->castDefault($column);
        }

        return $defaultValue;
    }

    /**
     * Cast default value based on column type. Required to prevent conflicts when not nullable
     * column added to existed table with data in.
     *
     * @param AbstractColumn $column
     *
     * @return bool|float|int|mixed|string
     */
    private function castDefault(AbstractColumn $column)
    {
        if ($column->abstractType() == 'timestamp' || $column->abstractType() == 'datetime') {
            $driver = $this->tableSchema->driver();

            return $driver::DEFAULT_DATETIME;
        }

        if ($column->abstractType() == 'enum') {
            //We can use first enum value as default
            return $column->getEnumValues()[0];
        }

        if ($column->abstractType() == 'json') {
            return '{}';
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
}
