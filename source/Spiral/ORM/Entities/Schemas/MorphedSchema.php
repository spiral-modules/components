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
use Spiral\ORM\RecordEntity;

/**
 * {@inheritdoc}
 *
 * Provides common set of functionality for polymorphic relations. Polymorphic relations declare
 * their relation to interfaces not to record classes. Record role names will be used to resolve
 * outer records.
 *
 * @see RecordSchema::getRole()
 */
abstract class MorphedSchema extends RelationSchema
{
    /**
     * {@inheritdoc}
     */
    public function isInversable()
    {
        //Morphed relations must control unique relations on lower level
        return !empty($this->definition[RecordEntity::INVERSE]) && $this->isReasonable();
    }

    /**
     * {@inheritdoc}
     */
    public function isReasonable()
    {
        return !empty($this->outerRecords());
    }

    /**
     * {@inheritdoc}
     */
    public function isSameDatabase()
    {
        foreach ($this->outerRecords() as $record) {
            if ($this->record->getDatabase() != $record->getDatabase()) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Method will ensure that all related records has same key type.
     *
     * @throws RelationSchemaException
     */
    public function getOuterKeyType()
    {
        $outerKeyType = null;
        foreach ($this->outerRecords() as $record) {
            if (!$record->tableSchema()->hasColumn($this->getOuterKey())) {
                throw new RelationSchemaException(
                    "Morphed relation ($this) requires outer key exists in every record ({$record})."
                );
            }

            $recordKeyType = $this->resolveAbstract(
                $record->tableSchema()->column($this->getOuterKey())
            );

            if (is_null($outerKeyType)) {
                $outerKeyType = $recordKeyType;
            }

            //Consistency of outer keys is strictly required
            if ($outerKeyType != $recordKeyType) {
                throw new RelationSchemaException(
                    "Morphed relation ({$this}) requires consistent outer key type ({$record}), "
                    . "expected '{$outerKeyType}' got '{$recordKeyType}'."
                );
            }
        }

        return $outerKeyType;
    }

    /**
     * Get every related outer record.
     *
     * @return RecordSchema[]
     */
    public function outerRecords()
    {
        $records = [];
        foreach ($this->builder->getRecords() as $record) {
            if ($record->isSubclassOf($this->getTarget()) && !$record->isAbstract()) {
                $records[] = $record;
            }
        }

        return $records;
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
        foreach ($this->outerRecords() as $record) {
            //We must remember how to relate morphed key value to outer record
            $definition[static::RELATION_TYPE][$record->getRole()] = $record->getName();
        }

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    protected function proposedDefinitions()
    {
        $options = parent::proposedDefinitions();

        foreach ($this->outerRecords() as $record) {
            //We can use first found record primary key to populate default value
            $options['outer:primaryKey'] = $record->getPrimaryKey();
            break;
        }

        return $options;
    }
}