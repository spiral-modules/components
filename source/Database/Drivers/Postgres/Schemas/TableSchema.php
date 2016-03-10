<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\Postgres\Schemas;

use Spiral\Database\Entities\Schemas\AbstractTable;

/**
 * Postgres table schema.
 */
class TableSchema extends AbstractTable
{
    /**
     * Found table sequences.
     *
     * @var array
     */
    private $sequences = [];

    /**
     * Sequence object name usually defined only for primary keys and required by ORM to correctly
     * resolve inserted row id.
     *
     * @var string|null
     */
    private $primarySequence = null;

    /**
     * Sequence object name usually defined only for primary keys and required by ORM to correctly
     * resolve inserted row id.
     *
     * @return string|null
     */
    public function getSequence()
    {
        return $this->primarySequence;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadColumns()
    {
        //Required for constraints fetch
        $tableOID = $this->driver->query('SELECT oid FROM pg_class WHERE relname = ?', [
            $this->getName(),
        ])->fetchColumn();

        //Collecting all candidates
        $query = 'SELECT * FROM information_schema.columns '
            . 'JOIN pg_type ON (pg_type.typname = columns.udt_name) WHERE table_name = ?';

        $columnsQuery = $this->driver->query($query, [$this->getName()]);
        foreach ($columnsQuery->bind('column_name', $columnName) as $column) {
            if (preg_match(
                '/^nextval\([\'"]([a-z0-9_"]+)[\'"](?:::regclass)?\)$/i',
                $column['column_default'],
                $matches
            )) {
                $this->sequences[$columnName] = $matches[1];
            }

            $this->registerColumn(
                $this->columnSchema($columnName, $column + ['tableOID' => $tableOID])
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadIndexes()
    {
        $query = "SELECT * FROM pg_indexes WHERE schemaname = 'public' AND tablename = ?";
        foreach ($this->driver->query($query, [$this->getName()]) as $index) {
            $index = $this->registerIndex(
                $this->indexSchema($index['indexname'], $index['indexdef'])
            );

            $conType = $this->driver->query(
                'SELECT contype FROM pg_constraint WHERE conname = ?', [$index->getName()]
            )->fetchColumn();

            if ($conType == 'p') {
                $this->setPrimaryKeys($index->getColumns());

                //We don't need primary index in this form
                $this->forgetIndex($index);

                if (is_array($this->primarySequence) && count($index->getColumns()) === 1) {
                    $column = $index->getColumns()[0];

                    if (isset($this->sequences[$column])) {
                        //We found our primary sequence
                        $this->primarySequence = $this->sequences[$column];
                    }
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadReferences()
    {
        //Mindblowing
        $query = 'SELECT tc.constraint_name, tc.table_name, kcu.column_name, rc.update_rule, '
            . 'rc.delete_rule, ccu.table_name AS foreign_table_name, '
            . "ccu.column_name AS foreign_column_name\n"
            . "FROM information_schema.table_constraints AS tc\n"
            . "JOIN information_schema.key_column_usage AS kcu\n"
            . "   ON tc.constraint_name = kcu.constraint_name\n"
            . "JOIN information_schema.constraint_column_usage AS ccu\n"
            . "   ON ccu.constraint_name = tc.constraint_name\n"
            . "JOIN information_schema.referential_constraints AS rc\n"
            . "   ON rc.constraint_name = tc.constraint_name\n"
            . "WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name=?";

        foreach ($this->driver->query($query, [$this->getName()]) as $reference) {
            $this->registerReference(
                $this->referenceSchema($reference['constraint_name'], $reference)
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function columnSchema($name, $schema = null)
    {
        return new ColumnSchema($this, $name, $schema);
    }

    /**
     * {@inheritdoc}
     */
    protected function indexSchema($name, $schema = null)
    {
        return new IndexSchema($this, $name, $schema);
    }

    /**
     * {@inheritdoc}
     */
    protected function referenceSchema($name, $schema = null)
    {
        return new ReferenceSchema($this, $name, $schema);
    }
}
