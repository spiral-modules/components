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
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ORM\Entities\SchemaBuilder;
use Spiral\ORM\Model;

/**
 * Performs analysis and schema building for one specific Model class.
 */
abstract class ModelSchema extends ReflectionEntity
{
    /**
     * Required to validly merge parent and children attributes.
     */
    const BASE_CLASS = Model::class;

    /**
     * @invisible
     * @var SchemaBuilder
     */
    protected $builder = null;

    /**
     * @param SchemaBuilder $builder Parent ODM schema (all other models).
     * @param string        $class   Class name.
     */
    public function __construct(SchemaBuilder $builder, $class)
    {
        $this->builder = $builder;
        parent::__construct($class);
    }

    /**
     * Source table. In case if table name not specified, ModelSchema will generate table name using class name.
     *
     * @return mixed
     */
    public function getTable()
    {
        $table = $this->property('table');

        if (empty($table)) {
            //We can guess table name
            $table = Inflector::tableize($this->getShortName());

            //Table names are plural by default
            return Inflector::pluralize($table);
        }

        return $table;
    }

    /**
     * Get database where model data should be stored in. Database alias will be resolved.
     *
     * @return mixed
     */
    public function getDatabase()
    {
        $database = $this->property('database');
        if (empty($database)) {
            $database = $this->builder->getORM()->config()['default'];
        }

        $aliases = $this->builder->getORM()->config()['aliases'];
        while (isset($aliases[$database])) {
            $database = $aliases[$database];
        }

        return $database;
    }

    /**
     * Get model declared schema (merged with parent model(s) values).
     *
     * @return array
     */
    protected function getSchema()
    {
        //Reading schema as property to inherit all values
        return $this->property('schema', true);
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
}