<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities\Schemas;

use Spiral\Core\Component;

/**
 * TableSchema helper used to store original table elements and run comparation between them.
 */
class TableState extends Component
{
    /**
     * @var string
     */
    private $name = '';

    /**
     * @var AbstractColumn[]
     */
    private $columns = [];

    /**
     * @var AbstractIndex[]
     */
    private $indexes = [];

    /**
     * @var AbstractReference[]
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
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Set table name. Operation will be applied at moment of saving.
     *
     * @param string $name
     */
    public function setName($name)
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
     * @return AbstractColumn[]
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
     * @return AbstractIndex[]
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
     * @return AbstractReference[]
     */
    public function getForeigns()
    {
        return $this->foreigns;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
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
     * @param string $name
     *
     * @return bool
     */
    public function knowsColumn($name)
    {
        return isset($this->columns[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function knowsIndex($name)
    {
        return isset($this->indexes[$name]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function knowsForeign($name)
    {
        return isset($this->foreigns[$name]);
    }

    /**
     * {@inheritdoc}
     *
     * Lookup is performed based on initial column name.
     */
    public function hasColumn($name)
    {
        return !empty($this->findColumn($name));
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(array $columns = [])
    {
        return !empty($this->findIndex($columns));
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeign($column)
    {
        return !empty($this->findForeign($column));
    }

    /**
     * Register new column element.
     *
     * @param AbstractColumn $column
     *
     * @return AbstractColumn
     */
    protected function registerColumn(AbstractColumn $column)
    {
        $this->columns[$column->getName()] = $column;

        return $column;
    }

    /**
     * Register new index element.
     *
     * @param AbstractIndex $index
     *
     * @return AbstractIndex
     */
    protected function registerIndex(AbstractIndex $index)
    {
        $this->indexes[$index->getName()] = $index;

        return $index;
    }

    /**
     * Register new foreign key element.
     *
     * @param AbstractReference $foreign
     *
     * @return AbstractReference
     */
    protected function registerReference(AbstractReference $foreign)
    {
        $this->foreigns[$foreign->getName()] = $foreign;

        return $foreign;
    }

    /**
     * Drop column from table schema.
     *
     * @param AbstractColumn $column
     *
     * @return $this
     */
    protected function forgetColumn(AbstractColumn $column)
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
     * @param AbstractIndex $index
     *
     * @return $this
     */
    protected function forgetIndex(AbstractIndex $index)
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
     * @param AbstractReference $foreign
     *
     * @return $this
     */
    protected function forgetForeign(AbstractReference $foreign)
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
     * @return null|AbstractColumn
     */
    protected function findColumn($name)
    {
        foreach ($this->columns as $column) {
            if ($column->getName() == $name) {
                return $column;
            }
        }

        return;
    }

    /**
     * Find index by it's columns or return null.
     *
     * @param array $columns
     *
     * @return null|AbstractIndex
     */
    protected function findIndex(array $columns)
    {
        foreach ($this->indexes as $index) {
            if ($index->getColumns() == $columns) {
                return $index;
            }
        }

        return;
    }

    /**
     * Find foreign key by it's column or return null.
     *
     * @param string $column
     *
     * @return null|AbstractReference
     */
    protected function findForeign($column)
    {
        foreach ($this->foreigns as $reference) {
            if ($reference->getColumn() == $column) {
                return $reference;
            }
        }

        return;
    }

    /**
     * Remount elements under their current name.
     *
     * @return self
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
     * @param TableState $source
     *
     * @return self
     */
    protected function syncSchema(TableState $source)
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
