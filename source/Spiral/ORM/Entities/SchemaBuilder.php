<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\ORM\Entities\Schemas\ModelSchema;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Model;
use Spiral\ORM\ORM;
use Spiral\Tokenizer\TokenizerInterface;

/**
 * Schema builder responsible for static analysis of existed ORM Models, their schemas, validations, related tables,
 * requested indexes and etc.
 */
class SchemaBuilder extends Component
{
    /**
     * Schema builder configuration includes mutators list and etc.
     */
    use ConfigurableTrait, LoggerTrait;

    /**
     * @var ModelSchema[]
     */
    private $models = [];

    /**
     * @var AbstractTable[]
     */
    private $tables = [];

    /**
     * @var ORM
     */
    protected $orm = null;

    /**
     * @param ORM                $orm
     * @param array              $config
     * @param TokenizerInterface $tokenizer
     */
    public function __construct(ORM $orm, array $config, TokenizerInterface $tokenizer)
    {
        $this->config = $config;
        $this->orm = $orm;

        $this->locateModels($tokenizer);
        $this->castRelations();
    }

    /**
     * @return ORM
     */
    public function getORM()
    {
        return $this->orm;
    }

    /**
     * Check if Model class known to schema builder.
     *
     * @param string $class
     * @return bool
     */
    public function hasModel($class)
    {
        return isset($this->models[$class]);
    }

    /**
     * Instance of ModelSchema associated with given class name.
     *
     * @param string $class
     * @return ModelSchema
     * @throws SchemaException
     */
    public function model($class)
    {
        if ($class == Model::class) {
            //No need to remember schema for abstract Document
            return new ModelSchema($this, Model::class);
        }

        if (!isset($this->models[$class])) {
            throw new SchemaException("Unknown model class '{$class}'.");
        }

        return $this->models[$class];
    }

    /**
     * @return ModelSchema[]
     */
    public function getModels()
    {
        return $this->models;
    }

    /**
     * Check if given table name was declared by one of models.
     *
     * @param string $database Table database.
     * @param string $table    Table name without prefix.
     * @return bool
     */
    public function hasTable($database, $table)
    {
        return isset($this->tables[$database . '/' . $table]);
    }

    /**
     * Declare table schema to be created when schema will be executed.
     *
     * @param string $database Table database.
     * @param string $table    Table name without prefix.
     * @return AbstractTable
     */
    public function table($database, $table)
    {
        if (isset($this->tables[$database . '/' . $table])) {
            return $this->tables[$database . '/' . $table];
        }

        $table = $this->orm->dbalDatabase($database)->table($table)->schema();
        if ($table instanceof LoggerAwareInterface) {
            $table->setLogger($this->logger());
        }

        return $this->tables[$database . '/' . $table] = $table;
    }

    /**
     * Get list of every declared table schema.
     *
     * @param bool $cascade Sort tables in order of dependency.
     * @return AbstractTable[]
     */
    public function getTables($cascade = true)
    {
        if (!$cascade) {
            return $this->tables;
        }

        $tables = $this->tables;
        uasort($tables, function (AbstractTable $tableA, AbstractTable $tableB) {
            if (in_array($tableA->getName(), $tableB->getDependencies())) {
                return true;
            }

            return count($tableB->getDependencies()) > count($tableA->getDependencies());
        });

        return array_reverse($tables);
    }

    public function relation()
    {
    }

    public function executeSchema()
    {
    }

    public function normalizeSchema()
    {
    }

    //----

    /**
     * Get all mutators associated with field type.
     *
     * @param string $type Field type.
     * @return array
     */
    public function getMutators($type)
    {
        return isset($this->config['mutators'][$type]) ? $this->config['mutators'][$type] : [];
    }

    /**
     * Get mutator alias if presented. Aliases used to simplify schema (accessors) definition.
     *
     * @param string $alias
     * @return string|array
     */
    public function mutatorAlias($alias)
    {
        if (!is_string($alias) || !isset($this->config['mutatorAliases'][$alias])) {
            return $alias;
        }

        return $this->config['mutatorAliases'][$alias];
    }

    /**
     * Locate every available Model class.
     *
     * @param TokenizerInterface $tokenizer
     */
    protected function locateModels(TokenizerInterface $tokenizer)
    {
        foreach ($tokenizer->getClasses(Model::class) as $class => $definition) {
            if ($class == Model::class) {
                continue;
            }

            $this->models[$class] = new ModelSchema($this, $class);
        }
    }

    protected function castRelations()
    {
        //        $inversedRelations = [];
        //        foreach ($this->models as $model) {
        //            if (!$model->isAbstract()) {
        //                $model->castRelations();
        //                foreach ($model->getRelations() as $relation) {
        //                    if ($relation->isInversable()) {
        //                        $inversedRelations[] = $relation;
        //                    }
        //                }
        //            }
        //        }
        //
        //        /**
        //         * We have to perform inversion after all relations was defined.
        //         *
        //         * @var RelationSchemaInterface $relation
        //         */
        //        foreach ($inversedRelations as $relation) {
        //            $relation->inverseRelation();
        //        }
    }
}