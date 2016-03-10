<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities\Schemas\Relations;

use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\ORM\Entities\Schemas\Relations\Traits\ColumnsTrait;
use Spiral\ORM\Entities\Schemas\RelationSchema;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

/**
 * ManyToMany relation declares that two records related to each other using pivot table data.
 * Relation allow to specify inner key (key in parent record), outer key (key in outer record),
 * pivot table name, names of pivot columns to store inner and outer key values and set of
 * additional columns. Relation allow specifying default WHERE statement for outer records and
 * pivot table separately.
 *
 * Example (User related to many Tag records):
 * - relation will create pivot table named "tag_user_map" (if allowed), where table name generated
 *   based on roles of inner and outer tables sorted in ABC order (you can change name)
 * - relation will create pivot key named "user_id" related to User primary key
 * - relation will create pivot key named "tag_id" related to Tag primary key
 * - relation will create unique index on "user_id" and "tag_id" columns if allowed
 * - relation will create foreign key "tag_user_map"."user_id" => "users"."id" if allowed
 * - relation will create foreign key "tag_user_map"."tag_id" => "tags"."id" if allowed
 * - relation will create additional columns in pivot table if any requested
 */
class ManyToManySchema extends RelationSchema
{
    /*
     * Relation may create custom columns in pivot table using Record schema format.
     */
    use ColumnsTrait;

    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = RecordEntity::MANY_TO_MANY;

    /**
     * Relation represent multiple records.
     */
    const MULTIPLE = true;

    /**
     * {@inheritdoc}
     *
     * When relation states that relation defines connection to interface relation will be switched
     * to ManyToManyMorphed.
     */
    const EQUIVALENT_RELATION = RecordEntity::MANY_TO_MORPHED;

    /**
     * Default postfix for pivot tables.
     */
    const PIVOT_POSTFIX = '_map';

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //Inner key of parent record will be used to fill "THOUGHT_INNER_KEY" in pivot table
        RecordEntity::INNER_KEY         => '{record:primaryKey}',
        //We are going to use primary key of outer table to fill "THOUGHT_OUTER_KEY" in pivot table
        //This is technically "inner" key of outer record, we will name it "outer key" for simplicity
        RecordEntity::OUTER_KEY         => '{outer:primaryKey}',
        //Name field where parent record inner key will be stored in pivot table, role + innerKey
        //by default
        RecordEntity::THOUGHT_INNER_KEY => '{record:role}_{definition:innerKey}',
        //Name field where inner key of outer record (outer key) will be stored in pivot table,
        //role + outerKey by default
        RecordEntity::THOUGHT_OUTER_KEY => '{outer:role}_{definition:outerKey}',
        //Set constraints in pivot table (foreign keys)
        RecordEntity::CONSTRAINT        => true,
        //@link https://en.wikipedia.org/wiki/Foreign_key
        RecordEntity::CONSTRAINT_ACTION => 'CASCADE',
        //Relation allowed to create indexes in pivot table
        RecordEntity::CREATE_INDEXES    => true,
        //Name of pivot table to be declared, default value is not stated as it will be generated
        //based on roles of inner and outer records
        RecordEntity::PIVOT_TABLE       => null,
        //Relation allowed to create pivot table
        RecordEntity::CREATE_PIVOT      => true,
        //Additional set of columns to be added into pivot table, you can use same column definition
        //type as you using for your records
        RecordEntity::PIVOT_COLUMNS     => [],
        //Set of default values to be used for pivot table
        RecordEntity::PIVOT_DEFAULTS    => [],
        //WHERE statement in a form of simplified array definition to be applied to pivot table
        //data.
        RecordEntity::WHERE_PIVOT       => [],
        //WHERE statement to be applied for data in outer data while loading relation data
        //can not be inversed. Attention, WHERE conditions not used in has(), link() and sync()
        //methods.
        RecordEntity::WHERE             => [],
    ];

    /**
     * {@inheritdoc}
     */
    public function inverseRelation()
    {
        //Many to many relation can be inversed pretty easily, we only have to swap inner keys
        //with outer keys, however WHERE conditions can not be inversed
        $this->outerRecord()->addRelation($this->definition[RecordEntity::INVERSE], [
            RecordEntity::MANY_TO_MANY      => $this->record->getName(),
            RecordEntity::PIVOT_TABLE       => $this->definition[RecordEntity::PIVOT_TABLE],
            RecordEntity::OUTER_KEY         => $this->definition[RecordEntity::INNER_KEY],
            RecordEntity::INNER_KEY         => $this->definition[RecordEntity::OUTER_KEY],
            RecordEntity::THOUGHT_INNER_KEY => $this->definition[RecordEntity::THOUGHT_OUTER_KEY],
            RecordEntity::THOUGHT_OUTER_KEY => $this->definition[RecordEntity::THOUGHT_INNER_KEY],
            RecordEntity::CONSTRAINT        => $this->definition[RecordEntity::CONSTRAINT],
            RecordEntity::CONSTRAINT_ACTION => $this->definition[RecordEntity::CONSTRAINT_ACTION],
            RecordEntity::CREATE_INDEXES    => $this->definition[RecordEntity::CREATE_INDEXES],
            RecordEntity::CREATE_PIVOT      => $this->definition[RecordEntity::CREATE_PIVOT],
            RecordEntity::PIVOT_COLUMNS     => $this->definition[RecordEntity::PIVOT_COLUMNS],
            RecordEntity::WHERE_PIVOT       => $this->definition[RecordEntity::WHERE_PIVOT],
        ]);
    }

    /**
     * Generate name of pivot table or fetch if from schema.
     *
     * @return string
     */
    public function getPivotTable()
    {
        if (isset($this->definition[RecordEntity::PIVOT_TABLE])) {
            return $this->definition[RecordEntity::PIVOT_TABLE];
        }

        //Generating pivot table name
        $names = [$this->record->getRole(), $this->outerRecord()->getRole()];
        asort($names);

        return implode('_', $names) . static::PIVOT_POSTFIX;
    }

    /**
     * Instance of AbstractTable associated with relation pivot table.
     *
     * @return AbstractTable
     */
    public function pivotSchema()
    {
        return $this->builder->declareTable(
            $this->record->getDatabase(),
            $this->getPivotTable()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildSchema()
    {
        if (!$this->definition[RecordEntity::CREATE_PIVOT]) {
            //No pivot table creation were requested, noting really to do
            return;
        }

        $pivotTable = $this->pivotSchema();

        //Thought outer key points to inner key in outer record (outer key)
        $outerKey = $pivotTable->column($this->definition[RecordEntity::THOUGHT_OUTER_KEY]);
        $outerKey->setType($this->getOuterKeyType());

        if ($this->hasMorphKey()) {
            //ManyToManyMorphed relation will cause creation set of ManyToMany relations
            //linking every possible morphed record with parent record
            $morphKey = $pivotTable->column($this->getMorphKey());
            $morphKey->string(static::MORPH_COLUMN_SIZE);
        }

        //Thought inner key points to inner key in parent record
        $innerKey = $pivotTable->column($this->definition[RecordEntity::THOUGHT_INNER_KEY]);
        $innerKey->setType($this->getInnerKeyType());

        //Casting pivot table columns
        $this->castTable(
            $this->pivotSchema(),
            $this->definition[RecordEntity::PIVOT_COLUMNS],
            $this->definition[RecordEntity::PIVOT_DEFAULTS]
        );

        if (!$this->isConstrained() || $this->hasMorphKey()) {
            //Either not need to create constraint or it relation is polymorphic, we also
            //can't create indexes in this case
            return;
        }

        if ($this->isIndexed()) {
            //Unique index are added to pivot table keys, you can't link records multiple times
            //If you DO want to do that, please create necessary tables and indexes using migrations
            $pivotTable->unique(
                $this->definition[RecordEntity::THOUGHT_INNER_KEY],
                $this->definition[RecordEntity::THOUGHT_OUTER_KEY]
            );
        }

        //Inner pivot key = parent record inner key
        $foreignKey = $innerKey->references(
            $this->record->getTable(),
            $this->record->getPrimaryKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());

        //Outer pivot key = outer record inner key (outer key)
        $foreignKey = $outerKey->references(
            $this->outerRecord()->getTable(),
            $this->outerRecord()->getPrimaryKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());
    }

    /**
     * {@inheritdoc}
     */
    protected function normalizeDefinition()
    {
        $definition = parent::normalizeDefinition();

        $definition[RecordEntity::PIVOT_COLUMNS] = [];
        foreach ($this->pivotSchema()->getColumns() as $column) {
            //Let's include pivot table columns, it will help many to many loaded to map data correctly
            $definition[RecordEntity::PIVOT_COLUMNS][] = $column->getName();
        }

        //We must include pivot table database into data for easier access
        $definition[ORM::R_DATABASE] = $this->outerRecord()->getDatabase();

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    protected function clarifyDefinition()
    {
        parent::clarifyDefinition();
        if (empty($this->definition[RecordEntity::PIVOT_TABLE])) {
            $this->definition[RecordEntity::PIVOT_TABLE] = $this->getPivotTable();
        }

        if (!$this->isSameDatabase()) {
            throw new RelationSchemaException(
                "Many-to-Many relation can create relations ({$this}) only to entities from same database."
            );
        }
    }
}
