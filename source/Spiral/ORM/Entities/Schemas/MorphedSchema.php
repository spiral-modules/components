<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas;

use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Model;

/**
 * {@inheritdoc}
 *
 * Provides common set of functionality for polymorphic relations. Polymorphic relations declare
 * their relation to interfaces not to model classes. Model role names will be used to resolve
 * outer records.
 *
 * @see ModelSchema::getRole()
 */
abstract class MorphedSchema extends RelationSchema
{
    /**
     * {@inheritdoc}
     */
    public function isInversable()
    {
        //Morphed relations must control unique relations on lower level
        return !empty($this->definition[Model::INVERSE]) && $this->isReasonable();
    }

    /**
     * {@inheritdoc}
     */
    public function isReasonable()
    {
        return !empty($this->outerModels());
    }

    /**
     * {@inheritdoc}
     */
    public function isSameDatabase()
    {
        foreach ($this->outerModels() as $record) {
            if ($this->model->getDatabase() != $record->getDatabase()) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Method will ensure that all related models has same key type.
     *
     * @throws RelationSchemaException
     */
    public function getOuterKeyType()
    {
        $outerKeyType = null;
        foreach ($this->outerModels() as $model) {
            if (!$model->tableSchema()->hasColumn($this->getOuterKey())) {
                throw new RelationSchemaException(
                    "Morphed relation ($this) requires outer key exists in every model ({$model})."
                );
            }

            $modelKeyType = $this->resolveAbstract(
                $model->tableSchema()->column($this->getOuterKey())
            );

            if (is_null($outerKeyType)) {
                $outerKeyType = $modelKeyType;
            }

            //Consistency of outer keys is strictly required
            if ($outerKeyType != $modelKeyType) {
                throw new RelationSchemaException(
                    "Morphed relation ({$this}) requires consistent outer key type ({$model}), "
                    . "expected '{$outerKeyType}' got '{$modelKeyType}'."
                );
            }
        }

        return $outerKeyType;
    }

    /**
     * Get every related outer model.
     *
     * @return ModelSchema[]
     */
    public function outerModels()
    {
        $models = [];
        foreach ($this->builder->getModels() as $model) {
            if ($model->isSubclassOf($this->getTarget()) && !$model->isAbstract()) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     */
    protected function clarifyDefinition()
    {
        parent::clarifyDefinition();

        if (!$this->isSameDatabase()) {
            throw new RelationSchemaException(
                "Morphed relations ({$this}) can only link entities from the same database."
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function normalizeDefinition()
    {
        $definition = parent::normalizeDefinition();

        $definition[static::RELATION_TYPE] = [];
        foreach ($this->outerModels() as $model) {
            //We must remember how to relate morphed key value to outer model
            $definition[static::RELATION_TYPE][$model->getRole()] = $model->getName();
        }

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    protected function proposedDefinitions()
    {
        $options = parent::proposedDefinitions();

        foreach ($this->outerModels() as $model) {
            //We can use first found model primary key to populate default value
            $options['outer:primaryKey'] = $model->getPrimaryKey();
            break;
        }

        return $options;
    }
}