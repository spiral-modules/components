<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities\Schemas;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Database\Schemas\TableInterface;

/**
 * @method AbstractColumn primary($column)
 * @method AbstractColumn bigPrimary($column)
 * @method AbstractColumn enum($column, array $values)
 * @method AbstractColumn string($column, $length = 255)
 * @method AbstractColumn decimal($column, $precision, $scale)
 * @method AbstractColumn boolean($column)
 * @method AbstractColumn integer($column)
 * @method AbstractColumn tinyInteger($column)
 * @method AbstractColumn bigInteger($column)
 * @method AbstractColumn text($column)
 * @method AbstractColumn tinyText($column)
 * @method AbstractColumn longText($column)
 * @method AbstractColumn double($column)
 * @method AbstractColumn float($column)
 * @method AbstractColumn datetime($column)
 * @method AbstractColumn date($column)
 * @method AbstractColumn time($column)
 * @method AbstractColumn timestamp($column)
 * @method AbstractColumn binary($column)
 * @method AbstractColumn tinyBinary($column)
 * @method AbstractColumn longBinary($column)
 */
abstract class AbstractTable extends Component implements TableInterface, LoggerAwareInterface
{
    /**
     * Update table primary keys index. Attention, this change is not possible after table is created,
     * additionally, ColumnSchema will automatically call this method with it's own name on setting
     * "primary" or "bigPrimary" types. Use this method only in cases where you have to define compound
     * primary index (beware, spiral ORM/ActiveRecord) does not support compound primary indexes.
     *
     * Attention, we recommend you do not use this method as different databases will behave differently
     * on primary indexes like that.
     *
     * @param array|mixed $columns Array or comma separated set of column names.
     * @return $this
     */
    public function setPrimaryKeys($columns)
    {
        $this->primaryKeys = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Get column from declared schema or create new one. Newly declared columns will be applied to
     * table structure on save() method call.
     *
     * @param string $column Column name.
     * @return AbstractColumn
     */
    public function column($column)
    {
        if (!isset($this->columns[$column])) {
            $this->columns[$column] = $this->driver->columnSchema($this, $column);
        }

        return $this->columns[$column];
    }

    /**
     * Alias for TableSchema->column(). Get column from declared schema or create new one. Newly
     * declared columns will be applied to table structure on save() method call.
     *
     * @param string $column
     * @return AbstractColumn
     */
    public function __get($column)
    {
        return $this->column($column);
    }

    /**
     * Shorter path for declaring column type. Using this method will emulate
     * TableSchema->column(name)->type(arguments) call. Newly declared columns will be applied to
     * table structure on save() method call.
     *
     * @param string $type      Desired column type.
     * @param array  $arguments Type specific parameters.
     * @return AbstractColumn
     */
    public function __call($type, array $arguments)
    {
        return call_user_func_array([$this->column($arguments[0]), $type], array_slice($arguments, 1));
    }



    /**
     * Check if table has existed or declared index by it's columns, to additionally check index type
     * use hasUnique() method. Method support both array column list, and dynamic column arguments
     * (comma separated). Columns order does matter!
     *
     * Example:
     * $table->hasIndex('userID', 'tokenID');
     * $table->hasIndex(array('userID', 'tokenID'));
     *
     * @param mixed|array $columns Column #1 or columns list array.
     * @return bool
     */
    public function hasIndex(array $columns = [])
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return (bool)$this->findIndex($columns);
    }

    /**
     * Check if table has existed or declared index by it's columns. Method is alias for hasIndex()
     * with additional check for unique indexes. Method support both array column list, and dynamic
     * column arguments (comma separated). Columns order does matter!
     *
     * Example:
     * $table->hasUnique('userID', 'tokenID');
     * $table->hasUnique(array('userID', 'tokenID'));
     *
     * @param mixed|array $columns Column #1 or columns list array.
     * @return bool
     */
    public function hasUnique(array $columns = [])
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        if (!$this->hasIndex($columns)) {
            return false;
        }

        return $this->findIndex($columns)->isUnique();
    }

    /**
     * Get all declared indexes. This list may be not identical to dbIndexes property as it will
     * represent desired table state.
     *
     * @return AbstractIndex[]
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * Get index from declared schema or create new one. Every index can be identified by set of
     * column(s), such columns can be provided as comma separated string arguments or array. Newly
     * declared indexes will be applied to table structure on save() method call. Attention, this
     * methods can return UNIQUE indexes, however it will declared NOT UNIQUE indexes upon creation.
     * Use separate method "unique for that", or combination index(columns)->unique(false) to reset
     * index from unique to non unique.
     *
     * Example:
     * $table->index('key');
     * $table->index('key', 'key2');
     * $table->index(array('key', 'key2'));
     *
     * @param mixed $columns Column name, or array of columns.
     * @return AbstractIndex|null
     */
    public function index($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        if ($index = $this->findIndex($columns)) {
            return $index;
        }

        $index = $this->driver->indexSchema($this, false);
        $index->columns($columns)->unique(false);

        //Adding to declared schema
        $this->indexes[$index->getName()] = $index;

        return $index;
    }

    /**
     * Get unique index from declared schema or create new one. Every index can be identified by set
     * of column(s), such columns can be provided as comma separated string arguments or array. Newly
     * declared indexes will be applied to table structure on save() method call. All indexes fetched
     * by this method will be automatically forces with UNIQUE type.
     *
     * Method is alias for: TableSchema->index(columns)->unique();
     *
     * Example:
     * $table->unique('key');
     * $table->unique('key', 'key2');
     * $table->unique(array('key', 'key2'));
     *
     * @param mixed $columns Column name, or array of columns.
     * @return AbstractColumn|null
     */
    public function unique($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->index($columns)->unique();
    }

    /**
     * Internal helper method used to find foreign key constrain by column name.
     *
     * @param string $column
     * @return AbstractReference|null
     */
    protected function findForeign($column)
    {
        foreach ($this->references as $reference) {
            if ($reference->getColumn() == $column) {
                return $reference;
            }
        }

        return null;
    }

    /**
     * Check if table has existed or declared foreign key references linked to specified column.
     *
     * @param string $column Column name.
     * @return bool
     */
    public function hasForeign($column)
    {
        return (bool)$this->findForeign($column);
    }

    /**
     * Get all declared foreign keys. This list may be not identical to dbReferences property as it
     * will represent desired table state.
     *
     * @return AbstractReference[]
     */
    public function getForeigns()
    {
        return $this->references;
    }

    /**
     * Get foreign key reference by column name or create new one. Newly declared references will be
     * applied to table structure on save() method call. Attention, make sure that both local and
     * referenced columns has same type.
     *
     * @param string $column Column name.
     * @return AbstractReference|null
     */
    public function foreign($column)
    {
        if ($foreign = $this->findForeign($column)) {
            return $foreign;
        }

        $foreign = $this->driver->referenceSchema($this, false);
        $foreign->column($column);

        //Adding to declared schema
        $this->references[$foreign->getName()] = $foreign;

        return $foreign;
    }

    /**
     * Rename existed column or change name of planned column. This operation is safe to use on
     * recurring bases as rename will be skipped if target column not exists or already named so.
     * Rename operation will be performed on save() method call.
     *
     * @param string $column Existed or planned column name.
     * @param string $name   New column name.
     * @return $this
     */
    public function renameColumn($column, $name)
    {
        foreach ($this->columns as $columnSchema) {
            if ($columnSchema->getName() == $column) {
                $columnSchema->name($name);
                break;
            }
        }

        return $this;
    }

    /**
     * Drop one or multiple columns from table schema, columns will be finally removed on save()
     * method call.
     *
     * @param string|array $column
     * @return $this
     */
    public function dropColumn($column)
    {
        $column = is_array($column) ? $column : func_get_args();
        foreach ($this->columns as $id => $columnSchema) {
            if (in_array($columnSchema->getName(), $column)) {
                unset($this->columns[$id]);
                break;
            }
        }

        return $this;
    }

    /**
     * Rename existed index or change name of planned index. This operation is safe to use on recurring
     * bases as rename will be skipped if target index not exists. Rename operation will be performed
     * on save() method call.
     *
     * @param string $index Existed index name.
     * @param string $name  New index name.
     * @return $this
     */
    public function renameIndex($index, $name)
    {
        foreach ($this->indexes as $indexSchema) {
            if ($indexSchema->getName() == $index) {
                $indexSchema->name($name);
                break;
            }
        }

        return $this;
    }

    /**
     * Drop one or multiple indexes from table schema, indexes will be finally removed on save()
     * method call. This method removes indexes by name, you can use TableSchema->index(column)->drop()
     * method to remove index defined by column(s).
     *
     * @param string|array $index
     * @return $this
     */
    public function dropIndex($index)
    {
        $index = is_array($index) ? $index : func_get_args();
        foreach ($this->indexes as $id => $indexSchema) {
            if (in_array($indexSchema->getName(), $index)) {
                unset($this->indexes[$id]);
                break;
            }
        }

        return $this;
    }

    /**
     * Drop one or multiple foreign keys from table schema, constraints will be finally removed on
     * save() method call. This method removes constraints by name, you can use
     * TableSchema->foreign(column)->drop() method to remove foreign key defined by column(s). As some
     * DBMS may not allow to define constraint name (SQLite), it's recommended to use "longer" path
     * for removal.
     *
     * @param string|array $foreign
     * @return $this
     */
    public function dropForeign($foreign)
    {
        $foreign = is_array($foreign) ? $foreign : func_get_args();
        foreach ($this->references as $id => $foreignSchema) {
            if (in_array($foreignSchema->getName(), $foreign)) {
                unset($this->references[$id]);
                break;
            }
        }

        return $this;
    }

    /**
     * Check if schema table was modified, will check every column, index and foreign key schemas.
     *
     * @return bool
     */
    public function hasChanges()
    {
        return $this->alteredColumns()
        || $this->alteredIndexes()
        || $this->alteredReferences()
        || $this->primaryKeys != $this->dbPrimaryKeys;
    }

    /**
     * List of column were altered by table schema manipulations, will include renamed, removed and
     * created columns.
     *
     * @return array|AbstractColumn[]
     */
    public function alteredColumns()
    {
        $altered = [];
        foreach ($this->columns as $column => $schema) {
            if (!isset($this->dbColumns[$column])) {
                $altered[$column] = $schema;
                continue;
            }

            if (!$schema->compare($this->dbColumns[$column])) {
                $altered[$column] = $schema;
            }
        }

        foreach ($this->dbColumns as $column => $schema) {
            if (!isset($this->columns[$column])) {
                //Going to be dropped
                $altered[$column] = null;
            }
        }

        return $altered;
    }

    /**
     * List of indexes were altered by table schema manipulations, will include renamed, removed and
     * newly created indexes.
     *
     * @return array|AbstractIndex[]
     */
    public function alteredIndexes()
    {
        $altered = [];
        foreach ($this->indexes as $index => $schema) {
            if (!isset($this->dbIndexes[$index])) {
                $altered[$index] = $schema;
                continue;
            }

            if (!$schema->compare($this->dbIndexes[$index])) {
                $altered[$index] = $schema;
            }
        }

        foreach ($this->dbIndexes as $index => $schema) {
            if (!isset($this->indexes[$index])) {
                //Going to be dropped
                $altered[$index] = null;
            }
        }

        return $altered;
    }

    /**
     * List of foreign keys constraints were altered by table schema manipulations, will include
     * renamed, removed and newly added constraints.
     *
     * @return array|AbstractReference[]
     */
    public function alteredReferences()
    {
        $altered = [];
        foreach ($this->references as $constraint => $schema) {
            if (!isset($this->dbReferences[$constraint])) {
                $altered[$constraint] = $schema;
                continue;
            }

            if (!$schema->compare($this->dbReferences[$constraint])) {
                $altered[$constraint] = $schema;
            }
        }

        foreach ($this->dbReferences as $constraint => $schema) {
            if (!isset($this->references[$constraint])) {
                //Going to be dropped
                $altered[$constraint] = null;
            }
        }

        return $altered;
    }

    /**
     * Get list of table names should exist before saving current table schema. This list includes
     * all tables schema references to. Method can be used to sort multiple table schemas in order
     * they has to be created without violating constraints. Attention, resulted table list will
     * include table prefixes.
     *
     * @return array
     */
    public function getDependencies()
    {
        $tables = [];

        foreach ($this->getForeigns() as $foreign) {
            $tables[] = $foreign->getForeignTable();
        }

        return $tables;
    }

    /**
     * Rename table. Operation will be applied immediately. Attention, this method receives new table
     * name without prefix. Appropriate prefix will be assigned automatically.
     *
     * @param string $name New table name without prefix.
     */
    public function rename($name)
    {
        if ($this->isExists()) {
            $this->driver->statement(\Spiral\interpolate(static::RENAME_STATEMENT, [
                'table' => $this->getName(true),
                'name'  => $this->driver->identifier($this->tablePrefix . $name)
            ]));
        }

        $this->name = $this->tablePrefix . $name;
    }

    /**
     * Drop table in database. This operation will be applied immediately. Double check that no other
     * tables has constraints related to dropped schema.
     */
    public function drop()
    {
        if (!$this->isExists()) {
            $this->columns = $this->dbColumns = $this->primaryKeys = $this->dbPrimaryKeys = [];
            $this->indexes = $this->dbIndexes = $this->references = $this->dbReferences = [];

            return;
        }

        $this->driver->statement(\Spiral\interpolate("DROP TABLE {table}", [
            'table' => $this->getName(true)
        ]));

        $this->exists = false;
        $this->columns = $this->dbColumns = $this->primaryKeys = $this->dbPrimaryKeys = [];
        $this->indexes = $this->dbIndexes = $this->references = $this->dbReferences = [];
    }

    /**
     * Apply all table schema changes to database, this methods either create table or update it's
     * columns, indexes and foreign keys one by one.
     */
    public function save()
    {
        if (!$this->isExists()) {
            $this->createSchema(true);
        } else {
            $this->hasChanges() && $this->updateSchema();
        }

        //Refreshing schema
        $this->exists = true;
        $this->dbPrimaryKeys = $this->primaryKeys;

        $columns = $this->columns;
        $indexes = $this->indexes;
        $references = $this->references;

        //Required due renames
        $this->columns = $this->dbColumns = [];
        foreach ($columns as $column) {
            $this->columns[$column->getName()] = $column;
            $this->dbColumns[$column->getName()] = clone $column;
        }

        $this->indexes = $this->dbIndexes = [];
        foreach ($indexes as $index) {
            $this->indexes[$index->getName()] = $index;
            $this->dbIndexes[$index->getName()] = clone $index;
        }

        $this->references = $this->dbReferences = [];
        foreach ($references as $reference) {
            $this->references[$reference->getName()] = $reference;
            $this->dbReferences[$reference->getName()] = clone $reference;
        }
    }

    /**
     * Generate table creation statement and execute it (if required). Method should return create
     * table sql query.
     *
     * @param bool $execute If true generated statement will be automatically executed.
     * @return string
     * @throws \Exception
     */
    protected function createSchema($execute = true)
    {
        $statement = [];
        $statement[] = "CREATE TABLE {$this->getName(true)} (";

        $inner = [];

        //Columns
        foreach ($this->columns as $column) {
            $inner[] = $column->sqlStatement();
        }

        //Primary key
        if (!empty($this->primaryKeys)) {
            $inner[] = 'PRIMARY KEY (' . join(', ', array_map(
                    [$this->driver, 'identifier'],
                    $this->primaryKeys
                )) . ')';
        }

        //Constraints
        foreach ($this->references as $reference) {
            $inner[] = $reference->sqlStatement();
        }

        $statement[] = "    " . join(",\n    ", $inner);
        $statement[] = ')';

        $statement = join("\n", $statement);

        $this->driver->beginTransaction();

        try {
            //Executing
            $execute && $this->driver->statement($statement);

            if ($execute) {
                //Not all databases support adding index while table creation, so we can do it after
                foreach ($this->indexes as $index) {
                    $this->doIndexAdd($index);
                }
            }
        } catch (\Exception $exception) {
            $this->driver->rollbackTransaction();
            throw $exception;
        }

        $this->driver->commitTransaction();

        return $statement;
    }

    /**
     * Perform set of atomic operations required to update table schema, such operations will include
     * column adding, removal, altering; index adding, removing altering; foreign key constraints
     * adding, removing and altering. All operations will be performed under common transaction,
     * failing one - will rollback others. Attention, rolling back transaction with schema modifications
     * can be not implemented in some databases.
     *
     * @throws SchemaException
     * @throws \Exception
     */
    protected function updateSchema()
    {
        if ($this->primaryKeys != $this->dbPrimaryKeys) {
            throw new SchemaException(
                "Primary keys can not be changed for already exists table."
            );
        }

        $this->driver->beginTransaction();
        try {
            foreach ($this->alteredColumns() as $name => $schema) {
                $dbColumn = isset($this->dbColumns[$name]) ? $this->dbColumns[$name] : null;

                if (empty($schema)) {
                    $this->logger()->info(
                        "Dropping column [{statement}] from table {table}.",
                        [
                            'statement' => $dbColumn->sqlStatement(),
                            'table'     => $this->getName(true)
                        ]
                    );

                    $this->doColumnDrop($dbColumn);
                    continue;
                }

                if (empty($dbColumn)) {
                    $this->logger()->info(
                        "Adding column [{statement}] into table {table}.",
                        [
                            'statement' => $schema->sqlStatement(),
                            'table'     => $this->getName(true)
                        ]
                    );

                    $this->doColumnAdd($schema);
                    continue;
                }

                //Altering
                $this->logger()->info(
                    "Altering column [{statement}] to [{new}] in table {table}.",
                    [
                        'statement' => $dbColumn->sqlStatement(),
                        'new'       => $schema->sqlStatement(),
                        'table'     => $this->getName(true)
                    ]
                );

                $this->doColumnChange($schema, $dbColumn);
            }

            foreach ($this->alteredIndexes() as $name => $schema) {
                $dbIndex = isset($this->dbIndexes[$name]) ? $this->dbIndexes[$name] : null;

                if (empty($schema)) {
                    $this->logger()->info(
                        "Dropping index [{statement}] from table {table}.",
                        [
                            'statement' => $dbIndex->sqlStatement(true),
                            'table'     => $this->getName(true)
                        ]
                    );

                    $this->doIndexDrop($dbIndex);
                    continue;
                }

                if (empty($dbIndex)) {
                    $this->logger()->info(
                        "Adding index [{statement}] into table {table}.",
                        [
                            'statement' => $schema->sqlStatement(false),
                            'table'     => $this->getName(true)
                        ]
                    );

                    $this->doIndexAdd($schema);
                    continue;
                }

                //Altering
                $this->logger()->info(
                    "Altering index [{statement}] to [{new}] in table {table}.",
                    [
                        'statement' => $dbIndex->sqlStatement(false),
                        'new'       => $schema->sqlStatement(false),
                        'table'     => $this->getName(true)
                    ]
                );

                $this->doIndexChange($schema, $dbIndex);
            }

            foreach ($this->alteredReferences() as $name => $schema) {
                $dbForeign = isset($this->dbReferences[$name]) ? $this->dbReferences[$name] : null;

                if (empty($schema)) {
                    $this->logger()->info(
                        "Dropping foreign key [{statement}] in table {table}.",
                        [
                            'statement' => $dbForeign->sqlStatement(),
                            'table'     => $this->getName(true)
                        ]
                    );

                    $this->doForeignDrop($this->dbReferences[$name]);
                    continue;
                }

                if (empty($dbForeign)) {
                    $this->logger()->info(
                        "Adding foreign key [{statement}] into table {table}.",
                        [
                            'statement' => $schema->sqlStatement(),
                            'table'     => $this->getName(true)
                        ]
                    );

                    $this->doForeignAdd($schema);
                    continue;
                }

                //Altering
                $this->logger()->info(
                    "Altering foreign key [{statement}] to [{new}] in table {table}.",
                    [
                        'statement' => $dbForeign->sqlStatement(),
                        'new'       => $schema->sqlStatement(),
                        'table'     => $this->getName(true)
                    ]
                );

                $this->doForeignChange($schema, $dbForeign);
            }
        } catch (\Exception $exception) {
            $this->driver->rollbackTransaction();
            throw $exception;
        }

        $this->driver->commitTransaction();
    }

    /**
     * Driver specific column add command.
     *
     * @param AbstractColumn $column
     */
    protected function doColumnAdd(AbstractColumn $column)
    {
        $this->driver->statement("ALTER TABLE {$this->getName(true)} "
            . "ADD COLUMN {$column->sqlStatement()}");
    }

    /**
     * Driver specific column remove (drop) command.
     *
     * @param AbstractColumn $column
     */
    protected function doColumnDrop(AbstractColumn $column)
    {
        //We have to erase all associated constraints
        foreach ($column->getConstraints() as $constraint) {
            $this->doConstraintDrop($constraint);
        }

        if ($this->hasForeign($column->getName())) {
            $this->doForeignDrop($this->foreign($column->getName()));
        }

        $this->driver->statement("ALTER TABLE {$this->getName(true)} "
            . "DROP COLUMN {$column->getName(true)}");
    }

    /**
     * Driver specific column altering command.
     *
     * @param AbstractColumn $column
     * @param AbstractColumn $dbColumn
     */
    abstract protected function doColumnChange(
        AbstractColumn $column,
        AbstractColumn $dbColumn
    );

    /**
     * Driver specific index adding command.
     *
     * @param AbstractIndex $index
     */
    protected function doIndexAdd(AbstractIndex $index)
    {
        $this->driver->statement("CREATE {$index->sqlStatement()}");
    }

    /**
     * Driver specific index remove (drop) command.
     *
     * @param AbstractIndex $index
     */
    protected function doIndexDrop(AbstractIndex $index)
    {
        $this->driver->statement("DROP INDEX {$index->getName(true)}");
    }

    /**
     * Driver specific index altering command, by default it will remove and add index.
     *
     * @param AbstractIndex $index
     * @param AbstractIndex $dbIndex
     */
    protected function doIndexChange(AbstractIndex $index, AbstractIndex $dbIndex)
    {
        $this->doIndexDrop($dbIndex);
        $this->doIndexAdd($index);
    }

    /**
     * Driver specific foreign key adding command.
     *
     * @param AbstractReference $foreign
     */
    protected function doForeignAdd(AbstractReference $foreign)
    {
        $this->driver->statement("ALTER TABLE {$this->getName(true)} ADD {$foreign->sqlStatement()}");
    }

    /**
     * Driver specific foreign key remove (drop) command.
     *
     * @param AbstractReference $foreign
     */
    protected function doForeignDrop(AbstractReference $foreign)
    {
        $this->driver->statement("ALTER TABLE {$this->getName(true)} "
            . "DROP CONSTRAINT {$foreign->getName(true)}");
    }

    /**
     * Remove simple column constraint, performed while dropping column.
     *
     * @param string $constraint
     */
    protected function doConstraintDrop($constraint)
    {
        $this->driver->statement("ALTER TABLE {$this->getName(true)} "
            . "DROP CONSTRAINT " . $this->driver->identifier($constraint));
    }

    /**
     * Driver specific foreign key altering command, by default it will remove and add foreign key.
     *
     * @param AbstractReference $foreign
     * @param AbstractReference $dbForeign
     */
    protected function doForeignChange(
        AbstractReference $foreign,
        AbstractReference $dbForeign
    ) {
        $this->doForeignDrop($dbForeign);
        $this->doForeignAdd($foreign);
    }
}