<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Schemas;

/**
 * TableSchema helper used to store original table elements and run comparation between them.
 *
 * Attention: this state IS MUTABLE!
 */
class TableState
{
    /**
     * @var string
     */
    private $name = '';

    /**
     * @var ColumnInterface[]
     */
    private $columns = [];

    /**
     * @var IndexInterface[]
     */
    private $indexes = [];

    /**
     * @var ReferenceInterface[]
     */
    private $foreigns = [];

    /**
     * Primary key columns are stored separately from other indexes and can be modified only during
     * table creation.
     *
     * @var array
     */
    private $primaryKeys = [];

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Set table name. Operation will be applied at moment of saving.
     *
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
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
     *
     * Array key points to initial element name.
     *
     * @return ColumnInterface[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * {@inheritdoc}
     *
     * Array key points to initial element name.
     *
     * @return IndexInterface[]
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * {@inheritdoc}
     *
     * Array key points to initial element name.
     *
     * @return ReferenceInterface[]
     */
    public function getForeigns()
    {
        return $this->foreigns;
    }

    /**
     * Set table primary keys.
     *
     * @param array $columns
     *
     * @return $this
     */
    public function setPrimaryKeys(array $columns)
    {
        $this->primaryKeys = $columns;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function knowsColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function knowsIndex(string $name): bool
    {
        return isset($this->indexes[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function knowsForeign(string $name): bool
    {
        return isset($this->foreigns[$name]);
    }

    /**
     * {@inheritdoc}
     *
     * Lookup is performed based on initial column name.
     */
    public function hasColumn(string $name): bool
    {
        return !empty($this->findColumn($name));
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(array $columns = []): bool
    {
        return !empty($this->findIndex($columns));
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeign($column): bool
    {
        return !empty($this->findForeign($column));
    }

    /**
     * Register new column element.
     *
     * @param ColumnInterface $column
     *
     * @return ColumnInterface
     */
    protected function registerColumn(ColumnInterface $column): ColumnInterface
    {
        $this->columns[$column->getName()] = $column;

        return $column;
    }

    /**
     * Register new index element.
     *
     * @param IndexInterface $index
     *
     * @return IndexInterface
     */
    protected function registerIndex(IndexInterface $index): IndexInterface
    {
        $this->indexes[$index->getName()] = $index;

        return $index;
    }

    /**
     * Register new foreign key element.
     *
     * @param ReferenceInterface $foreign
     *
     * @return ReferenceInterface
     */
    protected function registerReference(ReferenceInterface $foreign): ReferenceInterface
    {
        $this->foreigns[$foreign->getName()] = $foreign;

        return $foreign;
    }

    /**
     * Drop column from table schema.
     *
     * @param ColumnInterface $column
     *
     * @return self
     */
    protected function forgetColumn(ColumnInterface $column): TableState
    {
        foreach ($this->columns as $name => $columnSchema) {
            if ($columnSchema == $column) {
                unset($this->columns[$name]);
                break;
            }
        }

        return $this;
    }

    /**
     * Drop index from table schema using it's name or forming columns.
     *
     * @param IndexInterface $index
     *
     * @return self
     */
    protected function forgetIndex(IndexInterface $index): TableState
    {
        foreach ($this->indexes as $name => $indexSchema) {
            if ($indexSchema == $index) {
                unset($this->indexes[$name]);
                break;
            }
        }

        return $this;
    }

    /**
     * Drop foreign key from table schema using it's forming column.
     *
     * @param ReferenceInterface $foreign
     *
     * @return self
     */
    protected function forgetForeign(ReferenceInterface $foreign): TableState
    {
        foreach ($this->foreigns as $name => $foreignSchema) {
            if ($foreignSchema == $foreign) {
                unset($this->foreigns[$name]);
                break;
            }
        }

        return $this;
    }

    /**
     * Find column by it's name or return null.
     *
     * @param string $name
     *
     * @return null|ColumnInterface
     */
    protected function findColumn(string $name)
    {
        foreach ($this->columns as $column) {
            if ($column->getName() == $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Find index by it's columns or return null.
     *
     * @param array $columns
     *
     * @return null|IndexInterface
     */
    protected function findIndex(array $columns)
    {
        foreach ($this->indexes as $index) {
            if ($index->getColumns() == $columns) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Find foreign key by it's column or return null.
     *
     * @param string $column
     *
     * @return null|ReferenceInterface
     */
    protected function findForeign(string $column)
    {
        foreach ($this->foreigns as $reference) {
            if ($reference->getColumn() == $column) {
                return $reference;
            }
        }

        return null;
    }

    /**
     * Remount elements under their current name.
     */
    protected function remountElements()
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[$column->getName()] = $column;
        }

        $indexes = [];
        foreach ($this->indexes as $index) {
            $indexes[$index->getName()] = $index;
        }

        $references = [];
        foreach ($this->foreigns as $reference) {
            $references[$reference->getName()] = $reference;
        }

        $this->columns = $columns;
        $this->indexes = $indexes;
        $this->foreigns = $references;
    }

    /**
     * Re-populate schema elements using other state as source. Elements will be cloned under their
     * schema name.
     *
     * @param self $source
     *
     * @return self
     */
    protected function syncSchema(self $source): self
    {
        $this->name = $source->name;
        $this->primaryKeys = $source->primaryKeys;

        $this->columns = [];
        foreach ($source->columns as $name => $column) {
            $this->columns[$name] = clone $column;
        }

        $this->indexes = [];
        foreach ($source->indexes as $name => $index) {
            $this->indexes[$name] = clone $index;
        }

        $this->foreigns = [];
        foreach ($source->foreigns as $name => $reference) {
            $this->foreigns[$name] = clone $reference;
        }

        return $this;
    }
}
