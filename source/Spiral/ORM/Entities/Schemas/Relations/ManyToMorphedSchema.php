<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas\Relations;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\ORM\Entities\Schemas\MorphedSchema;
use Spiral\ORM\Entities\Schemas\Relations\Traits\ColumnsTrait;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\ORM;
use Spiral\ORM\Record;

/**
 * ManyToMorphed relation declares relation between parent record and set of outer records joined by
 * common interface. Relation allow to specify inner key (key in parent record), outer key (key in
 * outer records), morph key, pivot table name, names of pivot columns to store inner and outer key
 * values and set of additional columns. Relation DOES NOT to specify WHERE statement for outer
 * records. However you can specify where conditions for PIVOT table.
 *
 * You can declare this relation using same syntax as for ManyToMany except your target class
 * must be an interface.
 *
 * Attention, be very careful using morphing relations, you must know what you doing!
 * Attention #2, relation like that can not be preloaded!
 *
 * Example [Tag related to many TaggableInterface], relation name "tagged", relation requested to be
 * inversed using name "tags":
 * - relation will walk should every record implementing TaggableInterface to collect name and
 *   type of outer keys, if outer key is not consistent across records implementing this interface
 *   an exception will be raised, let's say that outer key is "id" in every record
 * - relation will create pivot table named "tagged_map" (if allowed), where table name generated
 *   based on relation name (you can change name)
 * - relation will create pivot key named "tag_ud" related to Tag primary key
 * - relation will create pivot key named "tagged_id" related to primary key of outer records,
 *   singular relation name used to generate key like that
 * - relation will create pivot key named "tagged_type" to store role of outer record
 * - relation will create unique index on "tag_id", "tagged_id" and "tagged_type" columns if allowed
 * - relation will create additional columns in pivot table if any requested
 *
 * Using in records:
 * You can use inversed relation as usual ManyToMany, however in Tag record relation access will be
 * little bit more complex - every linked record will create inner ManyToMany relation:
 * $tag->tagged->users->count(); //Where "users" is plural form of one outer records
 *
 * You can defined your own inner relation names by using MORPHED_ALIASES option when defining
 * relation.
 *
 * @see BelongsToMorhedSchema
 * @see ManyToManySchema
 */
class ManyToMorphedSchema extends MorphedSchema
{
    /**
     * Relation may create custom columns in pivot table using Record schema format.
     */
    use ColumnsTrait;

    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = Record::MANY_TO_MORPHED;

    /**
     * Relation represent multiple records.
     */
    const MULTIPLE = true;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //Association list between tables and roles, internal
        Record::MORPHED_ALIASES   => [],
        //Pivot table name will be generated based on singular relation name and _map postfix
        Record::PIVOT_TABLE       => '{name:singular}_map',
        //Inner key points to primary key of parent record by default
        Record::INNER_KEY         => '{record:primaryKey}',
        //By default, we are looking for primary key in our outer records, outer key must present
        //in every outer record and be consistent
        Record::OUTER_KEY         => '{outer:primaryKey}',
        //Linking pivot table and parent record
        Record::THOUGHT_INNER_KEY => '{record:role}_{definition:innerKey}',
        //Linking pivot table and outer records
        Record::THOUGHT_OUTER_KEY => '{name:singular}_{definition:outerKey}',
        //Declares what specific record pivot record linking to
        Record::MORPH_KEY         => '{name:singular}_type',
        //Set constraints in pivot table (foreign keys)
        Record::CONSTRAINT        => true,
        //@link https://en.wikipedia.org/wiki/Foreign_key
        Record::CONSTRAINT_ACTION => 'CASCADE',
        //Relation allowed to create indexes in pivot table
        Record::CREATE_INDEXES    => true,
        //Relation allowed to create pivot table
        Record::CREATE_PIVOT      => true,
        //Additional set of columns to be added into pivot table, you can use same column definition
        //type as you using for your records
        Record::PIVOT_COLUMNS     => [],
        //WHERE statement in a form of simplified array definition to be applied to pivot table
        //data
        Record::WHERE_PIVOT       => []
    ];

    /**
     * {@inheritdoc}
     *
     * Relation will be inversed to every associated record.
     */
    public function inverseRelation()
    {
        //WHERE conditions can not be inversed
        foreach ($this->outerRecords() as $record) {
            if (!$record->hasRelation($this->definition[Record::INVERSE])) {
                $record->addRelation(
                    $this->definition[Record::INVERSE],
                    [
                        Record::MANY_TO_MANY      => $this->record->getName(),
                        Record::PIVOT_TABLE       => $this->definition[Record::PIVOT_TABLE],
                        Record::OUTER_KEY         => $this->definition[Record::INNER_KEY],
                        Record::INNER_KEY         => $this->definition[Record::OUTER_KEY],
                        Record::THOUGHT_INNER_KEY => $this->definition[Record::THOUGHT_OUTER_KEY],
                        Record::THOUGHT_OUTER_KEY => $this->definition[Record::THOUGHT_INNER_KEY],
                        Record::MORPH_KEY         => $this->definition[Record::MORPH_KEY],
                        Record::CREATE_INDEXES    => $this->definition[Record::CREATE_INDEXES],
                        Record::CREATE_PIVOT      => $this->definition[Record::CREATE_PIVOT],
                        Record::PIVOT_COLUMNS     => $this->definition[Record::PIVOT_COLUMNS],
                        Record::WHERE_PIVOT       => $this->definition[Record::WHERE_PIVOT]
                    ]
                );
            }
        }
    }

    /**
     * Generate name of pivot table or fetch if from schema.
     *
     * @return string
     */
    public function getPivotTable()
    {
        return $this->definition[Record::PIVOT_TABLE];
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
        if (!$this->definition[Record::CREATE_PIVOT]) {
            //No pivot table creation were requested, noting really to do
            return;
        }

        $pivotTable = $this->pivotSchema();

        //Inner key points to our parent record
        $innerKey = $pivotTable->column($this->definition[Record::THOUGHT_INNER_KEY]);
        $innerKey->type($this->getInnerKeyType());

        if ($this->isIndexed()) {
            $innerKey->index();
        }

        //Morph key will store role name of outer records
        $morphKey = $pivotTable->column($this->getMorphKey());
        $morphKey->string(static::MORPH_COLUMN_SIZE);

        //Points to inner key of our outer records (outer key)
        $outerKey = $pivotTable->column($this->definition[Record::THOUGHT_OUTER_KEY]);
        $outerKey->type($this->getOuterKeyType());

        foreach ($this->definition[Record::PIVOT_COLUMNS] as $column => $definition) {
            //Addition pivot columns must be defined same way as in Record schema
            $this->castColumn($pivotTable->column($column), $definition);
        }

        //Complex index
        if ($this->isIndexed()) {
            //Complex index including 3 columns from pivot table
            $pivotTable->unique(
                $this->definition[Record::THOUGHT_INNER_KEY],
                $this->definition[Record::MORPH_KEY],
                $this->definition[Record::THOUGHT_OUTER_KEY]
            );
        }

        if ($this->isConstrained()) {
            $foreignKey = $innerKey->references(
                $this->record->getTable(),
                $this->record->getPrimaryKey()
            );

            $foreignKey->onDelete($this->getConstraintAction());
            $foreignKey->onUpdate($this->getConstraintAction());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function normalizeDefinition()
    {
        $definition = parent::normalizeDefinition();

        foreach ($this->outerRecords() as $record) {
            if (!in_array($record->getRole(), $definition[Record::MORPHED_ALIASES])) {
                //Let's remember associations between tables and roles
                $plural = Inflector::pluralize($record->getRole());
                $definition[Record::MORPHED_ALIASES][$plural] = $record->getRole();
            }

            //We must include pivot table database into data for easier access
            $definition[ORM::R_DATABASE] = $record->getDatabase();
        }

        //Let's include pivot table columns
        $definition[Record::PIVOT_COLUMNS] = [];
        foreach ($this->pivotSchema()->getColumns() as $column) {
            $definition[Record::PIVOT_COLUMNS][] = $column->getName();
        }

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    protected function clarifyDefinition()
    {
        parent::clarifyDefinition();
        if (!$this->isSameDatabase()) {
            throw new RelationSchemaException(
                "Many-to-Many morphed relation can create relations ({$this}) "
                . "only to entities from same database."
            );
        }
    }
}