<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Database\Schemas\AbstractColumn;
use Spiral\ORM\Entities\SchemaBuilder;
use Spiral\ORM\Exceptions\RecordSchemaException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;
use Spiral\ORM\Schemas\RelationInterface;

/**
 * Generic (abstract) implementation of relation schema. Used for basic ORM relations.
 */
abstract class RelationSchema implements RelationInterface
{
    /**
     * Must contain relation type, this constant is required to fetch outer record(s) class name
     * from relation definition.
     */
    const RELATION_TYPE = null;

    /**
     * Relation represent multiple records.
     */
    const MULTIPLE = false;

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
     * @see RecordSchema::getRole()
     */
    const MORPH_COLUMN_SIZE = 32;

    /**
     * @var string
     */
    private $name = '';

    /**
     * Name of target record or interface. Must be fetched from definition.
     *
     * @var string
     */
    private $target = '';

    /**
     * Definition specified by record schema or another definition.
     *
     * @var array
     */
    protected $definition = [];

    /**
     * Most of relations provides ability to specify many different configuration options, such
     * as key names, pivot table schemas, foreign key request, ability to be nullabe and etc.
     *
     * To simple schema definition in real projects we can fill some of this values automatically
     * based on some "environment" values such as parent/outer record table, role name, primary key
     * and etc.
     *
     * Example:
     * ActiveRecord::INNER_KEY => '{outer:role}_{outer:primaryKey}'
     *
     * Result:
     * Outer Record is User with primary key "id" => "user_id"
     *
     * @invisible
     *
     * @var array
     */
    protected $defaultDefinition = [];

    /**
     * @invisible
     *
     * @var SchemaBuilder
     */
    protected $builder = null;

    /**
     * @invisible
     *
     * @var RecordSchema
     */
    protected $record = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        SchemaBuilder $builder,
        RecordSchema $owner,
        $name,
        array $definition
    ) {
        $this->builder = $builder;
        $this->record = $owner;

        $this->name = $name;

        //We can use definition type to fetch target outer record or interface
        $this->target = $definition[static::RELATION_TYPE];

        $this->definition = $definition;
        if ($this->hasEquivalent()) {
            return;
        }

        if (!class_exists($this->target) && !interface_exists($this->target)) {
            throw new RelationSchemaException(
                "Unable to build relation from '{$this->record}' to undefined target '{$this->target}'."
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
     * Get name or target to be related to.
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Relation represent multiple records.
     *
     * @return bool
     */
    public function isMultiple()
    {
        return static::MULTIPLE;
    }

    /**
     * {@inheritdoc}
     */
    public function hasEquivalent()
    {
        if (empty(static::EQUIVALENT_RELATION)) {
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
                "Relation '{$this->record}'.'{$this}' does not have equivalents."
            );
        }

        //Let's convert to polymorphic
        $definition = [
                static::EQUIVALENT_RELATION => $this->target,
            ] + $this->definition;

        unset($definition[static::RELATION_TYPE]);

        //Usually when relation declared as polymorphic
        return $this->builder->relationSchema($this->record, $this->name, $definition);
    }

    /**
     * {@inheritdoc}
     */
    public function isInversable()
    {
        if (empty($this->definition[RecordEntity::INVERSE]) || !$this->isReasonable()) {
            return false;
        }

        $inversed = $this->definition[RecordEntity::INVERSE];
        if (is_array($inversed)) {
            //Some relations requires not only inversed relation name but also type
            $inversed = $inversed[1];
        }

        //We must prevent duplicate relations
        return !$this->outerRecord()->hasRelation($inversed);
    }

    /**
     * {@inheritdoc}
     */
    public function isReasonable()
    {
        //Relation is only reasonable when outer record is not abstract and does not have relation
        //under same name
        return !$this->outerRecord()->isAbstract();
    }

    /**
     * Some relations may not have any associated record, which means that inner/outer key must
     * support nullable values. Records must allow setting fields like that to null.
     *
     * @return bool
     */
    public function isNullable()
    {
        if (array_key_exists(RecordEntity::NULLABLE, $this->definition)) {
            return $this->definition[RecordEntity::NULLABLE];
        }

        return false;
    }

    /**
     * Indication that relation has declared morph key. This means that request to outer data must
     * not only include inner key, but it must state value of morph key (usually record role name).
     *
     * Most of relations like that created automatically as inversion of polymorphic relation to
     * it's related record(s).
     *
     * @return bool
     */
    public function hasMorphKey()
    {
        return !empty($this->definition[RecordEntity::MORPH_KEY]);
    }

    /**
     * Name of declared morph key.
     *
     * @return string
     */
    public function getMorphKey()
    {
        if (isset($this->definition[RecordEntity::MORPH_KEY])) {
            return $this->definition[RecordEntity::MORPH_KEY];
        }

        return;
    }

    /**
     * Indication that relation allowed to create indexes in outer or inner tables.
     *
     * @return bool
     */
    public function isIndexed()
    {
        return !empty($this->definition[RecordEntity::CREATE_INDEXES]);
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

        if (array_key_exists(RecordEntity::CONSTRAINT, $this->definition)) {
            return $this->definition[RecordEntity::CONSTRAINT];
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
        if (array_key_exists(RecordEntity::CONSTRAINT_ACTION, $this->definition)) {
            return $this->definition[RecordEntity::CONSTRAINT_ACTION];
        }

        return;
    }

    /**
     * Check if parent and related records belongs to same database, it will allow ORM to use joins
     * to preload or filter by related data.
     *
     * @return bool
     *
     * @throws SchemaException
     */
    public function isSameDatabase()
    {
        if (!$this->builder->hasRecord($this->target)) {
            //Usually it tells us that relation relates to many different records (polymorphic)
            //We can't clearly say
            return false;
        }

        //Databases must be the same
        return $this->record->getDatabase() == $this->outerRecord()->getDatabase();
    }

    /**
     * Declared inner key. Must return null if no key defined or required.
     *
     * @return null|string
     */
    public function getInnerKey()
    {
        if (isset($this->definition[RecordEntity::INNER_KEY])) {
            return $this->definition[RecordEntity::INNER_KEY];
        }

        return;
    }

    /**
     * Declared outer key. Must return null if no key defined or required.
     *
     * @return null|string
     */
    public function getOuterKey()
    {
        if (isset($this->definition[RecordEntity::OUTER_KEY])) {
            return $this->definition[RecordEntity::OUTER_KEY];
        }

        return;
    }

    /**
     * Calculates abstract type of inner key. Must return null if no key defined or required.
     * Primary types will be converted to appropriate sized integers.
     *
     * @return null|string
     *
     * @throws SchemaException
     */
    public function getInnerKeyType()
    {
        if (empty($innerKey = $this->getInnerKey())) {
            return;
        }

        return $this->resolveAbstract(
            $this->record->tableSchema()->column($innerKey)
        );
    }

    /**
     * Calculates abstract type of outer key. Must return null if no key defined or required.
     * Primary types will be converted to appropriate sized integers.
     *
     * @return null|string
     *
     * @throws SchemaException
     */
    public function getOuterKeyType()
    {
        if (empty($outerKey = $this->getOuterKey())) {
            return;
        }

        return $this->resolveAbstract(
            $this->outerRecord()->tableSchema()->column($outerKey)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeSchema()
    {
        return [
            ORM::R_TYPE       => static::RELATION_TYPE,
            ORM::R_DEFINITION => $this->normalizeDefinition(),
        ];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

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
            $definition[RecordEntity::CONSTRAINT],
            $definition[RecordEntity::CONSTRAINT_ACTION],
            $definition[RecordEntity::CREATE_PIVOT],
            $definition[RecordEntity::INVERSE],
            $definition[RecordEntity::CREATE_INDEXES]
        );

        return $definition;
    }

    /**
     * Will specify missing fields in relation definition using default definition options. Such
     * options are dynamic and populated based on values fetched from related records.
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
            'name'              => $this->name,
            //Relation name in plural form
            'name:plural'       => Inflector::pluralize($this->name),
            //Relation name in singular form
            'name:singular'     => Inflector::singularize($this->name),
            //Parent record role name
            'record:role'       => $this->record->getRole(),
            //Parent record table name
            'record:table'      => $this->record->getTable(),
            //Parent record primary key
            'record:primaryKey' => $this->record->getPrimaryKey(),
        ];

        //Some options may use values declared in other definition fields
        $proposed = [
            RecordEntity::OUTER_KEY   => 'outerKey',
            RecordEntity::INNER_KEY   => 'innerKey',
            RecordEntity::PIVOT_TABLE => 'pivotTable',
        ];

        foreach ($proposed as $property => $alias) {
            if (isset($this->definition[$property])) {
                //Let's create some default options based on user specified values
                $options['definition:' . $alias] = $this->definition[$property];
            }
        }

        if ($this->builder->hasRecord($this->target)) {
            $options = $options + [
                    //Outer role name
                    'outer:role'       => $this->outerRecord()->getRole(),
                    //Outer record table
                    'outer:table'      => $this->outerRecord()->getTable(),
                    //Outer record primary key
                    'outer:primaryKey' => $this->outerRecord()->getPrimaryKey(),
                ];
        }

        return $options;
    }

    /**
     * Get RecordSchema to be associated with, method must throw an exception if outer record not
     * found.
     *
     * @return RecordSchema
     *
     * @throws RelationSchemaException
     * @throws SchemaException
     * @throws RecordSchemaException
     */
    protected function outerRecord()
    {
        if (!$this->builder->hasRecord($this->target)) {
            throw new RelationSchemaException(
                "Undefined outer record '{$this->target}' in relation '{$this->record}'.'{$this}'."
            );
        }

        return $this->builder->record($this->target);
    }

    /**
     * Resolve correct abstract type to represent inner or outer key. Primary types will be
     * converted to appropriate sized integers.
     *
     * @param AbstractColumn $column
     *
     * @return string
     */
    protected function resolveAbstract(AbstractColumn $column)
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
