<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM;

use Spiral\Core\Component;
use Spiral\Database\Schemas\AbstractTable;
use Spiral\ORM\Schemas\ModelSchema;
use Spiral\ORM\Schemas\RelationSchemaInterface;
use Spiral\Tokenizer\TokenizerInterface;

class SchemaBuilder extends Component
{
    /**
     * Schema generating configuration.
     *
     * @var array
     */
    protected $config = [];

    /**
     * ORM component instance.
     *
     * @var ORM
     */
    protected $orm = null;

    /**
     * Found ActiveRecord schemas.
     *
     * @var ModelSchema[]
     */
    protected $models = [];

    /**
     * All declared tables.
     *
     * @var array
     */
    public $tables = [];

    /**
     * New ORM Schema reader instance.
     *
     * @param array              $config
     * @param ORM                $orm
     * @param TokenizerInterface $tokenizer
     */
    public function __construct(array $config, ORM $orm, TokenizerInterface $tokenizer)
    {
        $this->config = $config;
        $this->orm = $orm;

        foreach ($tokenizer->getClasses(ActiveRecord::class) as $class => $definition)
        {
            if ($class == ActiveRecord::class)
            {
                continue;
            }

            $this->models[$class] = new ModelSchema($class, $this);
        }

        $inversedRelations = [];
        foreach ($this->models as $model)
        {
            if (!$model->isAbstract())
            {
                $model->castRelations();
                foreach ($model->getRelations() as $relation)
                {
                    if ($relation->isInversable())
                    {
                        $inversedRelations[] = $relation;
                    }
                }
            }
        }

        /**
         * We have to perform inversion after all relations was defined.
         *
         * @var RelationSchemaInterface $relation
         */
        foreach ($inversedRelations as $relation)
        {
            $relation->inverseRelation();
        }
    }

    /**
     * Get associated ORM component.
     *
     * @return ORM
     */
    public function getORM()
    {
        return $this->orm;
    }

    /**
     * Get ActiveRecord model schema by class name.
     *
     * @param string $class Class name.
     * @return null|ModelSchema
     */
    public function modelSchema($class)
    {
        if ($class == ActiveRecord::class)
        {
            return new ModelSchema(ActiveRecord::class, $this);
        }

        if (!isset($this->models[$class]))
        {
            return null;
        }

        return $this->models[$class];
    }

    /**
     * All fetched entity schemas.
     *
     * @return ModelSchema[]
     */
    public function getModelSchemas()
    {
        return $this->models;
    }

    /**
     * Get mutators for column with specified abstract or column type.
     *
     * @param string $type Column abstract type.
     * @return array
     */
    public function getMutators($type)
    {
        return isset($this->config['mutators'][$type]) ? $this->config['mutators'][$type] : [];
    }

    /**
     * Get mutator alias.
     *
     * @param string $alias
     * @return string|array|null
     */
    public function processAlias($alias)
    {
        if (!is_string($alias) || !isset($this->config['mutatorAliases'][$alias]))
        {
            return $alias;
        }

        return $this->config['mutatorAliases'][$alias];
    }

    /**
     * Declare table schema to be created.
     *
     * @param string $database
     * @param string $table
     * @return AbstractTable
     */
    public function table($database, $table)
    {
        if (isset($this->tables[$database . '/' . $table]))
        {
            return $this->tables[$database . '/' . $table];
        }

        $schema = $this->orm->getDatabase($database)->table($table)->schema();

        return $this->tables[$database . '/' . $table] = $schema;
    }

    /**
     * Get list of all declared tables. Cascade parameter will sort tables in order of their self
     * dependencies.
     *
     * @param bool $cascade
     * @return AbstractTable[]
     */
    public function getTables($cascade = true)
    {
        if ($cascade)
        {
            $tables = $this->tables;
            uasort($tables, function (AbstractTable $tableA, AbstractTable $tableB)
            {
                return in_array($tableA->getName(), $tableB->getDependencies())
                || count($tableB->getDependencies()) > count($tableA->getDependencies());
            });

            return array_reverse($tables);
        }

        return $this->tables;
    }

    /**
     * Get appropriate relation schema based on provided definition.
     *
     * @param ModelSchema $model
     * @param string      $name
     * @param array       $definition
     * @return RelationSchemaInterface
     */
    public function relationSchema(ModelSchema $model, $name, array $definition)
    {
        if (empty($definition))
        {
            throw new ORMException("Relation definition can not be empty.");
        }

        reset($definition);
        $type = key($definition);

        $relation = $this->orm->relationSchema($type, $this, $model, $name, $definition);
        if ($relation->hasEquivalent())
        {
            return $relation->createEquivalent();
        }

        return $relation;
    }

    /**
     * Perform schema reflection to database(s). All declared tables will created or altered. Only
     * tables linked to non abstract models and model with active schema parameter will be executed.
     *
     * Schema builder will thrown an exception if table linked to model with disabled schema has
     * changed columns, however indexes and foreign keys will not cause such exception.
     *
     * @throws ORMException
     */
    public function executeSchema()
    {
        foreach ($this->getTables(true) as $table)
        {
            foreach ($this->models as $model)
            {
                if ($model->tableSchema() != $table)
                {
                    continue;
                }

                if ($model->isAbstract())
                {
                    //Model is abstract, meaning we are not going to perform any table related
                    //operation
                    continue 2;
                }

                if ($model->isActiveSchema())
                {
                    //Model has active schema, we are good
                    break;
                }

                //We have to thrown an exception if model with ACTIVE_SCHEMA = false requested
                //any column change (for example via external relation)
                if (!empty($columns = $table->alteredColumns()))
                {
                    $names = [];
                    foreach ($columns as $column)
                    {
                        $names[] = $column->getName(true);
                    }

                    $names = join(', ', $names);

                    throw new ORMException(
                        "Unable to alter '{$table->getName()}' columns ({$names}), "
                        . "associated model stated ACTIVE_SCHEMA = false."
                    );
                }

                continue 2;
            }

            $table->save();
        }
    }

    /**
     * Normalize ODM schema and export it to be used by ODM component and all documents.
     *
     * @return array
     */
    public function normalizeSchema()
    {
        $schema = [];

        foreach ($this->models as $model)
        {
            if ($model->isAbstract())
            {
                continue;
            }

            $recordSchema = [];

            $recordSchema[ORM::E_ROLE_NAME] = $model->getRoleName();
            $recordSchema[ORM::E_TABLE] = $model->getTable();
            $recordSchema[ORM::E_DB] = $model->getDatabase();
            $recordSchema[ORM::E_PRIMARY_KEY] = $model->getPrimaryKey();

            $recordSchema[ORM::E_COLUMNS] = $model->getDefaults();
            $recordSchema[ORM::E_HIDDEN] = $model->getHidden();
            $recordSchema[ORM::E_SECURED] = $model->getSecured();
            $recordSchema[ORM::E_FILLABLE] = $model->getFillable();

            $recordSchema[ORM::E_MUTATORS] = $model->getMutators();
            $recordSchema[ORM::E_VALIDATES] = $model->getValidates();

            //Relations
            $recordSchema[ORM::E_RELATIONS] = [];
            foreach ($model->getRelations() as $name => $relation)
            {
                $recordSchema[ORM::E_RELATIONS][$name] = $relation->normalizeSchema();
            }

            ksort($recordSchema);
            $schema[$model->getClass()] = $recordSchema;
        }

        return $schema;
    }
}