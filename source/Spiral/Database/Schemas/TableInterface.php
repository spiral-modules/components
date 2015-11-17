<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Schemas;

/**
 * Represent table schema with it's all columns, indexes and foreign keys.
 */
interface TableInterface
{
    /**
     * Check if table exists in database.
     *
     * @return bool
     */
    public function exists();

    /**
     * Store specific table name (may include prefix).
     *
     * @return string
     */
    public function getName();

    /**
     * Array of columns dedicated to primary index. Attention, this methods will ALWAYS return
     * array, even if there is only one primary key.
     *
     * @return array
     */
    public function getPrimaryKeys();

    /**
     * Check if table have specified column.
     *
     * @param string $name Column name.
     * @return bool
     */
    public function hasColumn($name);

    /**
     * Get all declared columns.
     *
     * @return ColumnInterface[]
     */
    public function getColumns();

    /**
     * Check if table has index related to set of provided columns. Columns order does matter!
     *
     * @param array $columns
     * @return bool
     */
    public function hasIndex(array $columns = []);

    /**
     * Get all table indexes.
     *
     * @return IndexInterface[]
     */
    public function getIndexes();

    /**
     * Check if table has foreign key related to table column.
     *
     * @param string $column Column name.
     * @return bool
     */
    public function hasForeign($column);

    /**
     * Get all table foreign keys.
     *
     * @return ReferenceInterface[]
     */
    public function getForeigns();

    /**
     * Get list of table names current schema depends on, must include every table linked using
     * foreign key or other constraint. Table names must not include database prefix if such
     * presented (meaning both tables must belong to the same database namespace).
     *
     * @return array
     */
    public function getDependencies();
}
