<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces\Entities\Schemas;

interface TableInterface
{
    /**
     * Check if table exists in database.
     *
     * @return bool
     */
    public function exists();

    /**
     * Table name (including prefix).
     *
     * @return string
     */
    public function getName();

    /**
     * Array of columns dedicated to primary index. Attention, this methods will ALWAYS return array,
     * even if there is only one primary key.
     *
     * @return array
     */
    public function getPrimaryKeys();

    /**
     * Check if table have specified column. Method will check column existence in "columns" attribute,
     * so it's not necessary that column exists in database table, it can be simply declared earlier.
     *
     * @param string $name Column name.
     * @return bool
     */
    public function hasColumn($name);

    /**
     * Get all declared columns. This list may be not identical to dbColumns property as it will
     * represent desired table state.
     *
     * @return ColumnInterface[]
     */
    public function getColumns();

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
    public function hasIndex(array $columns = []);

    /**
     * Get all declared indexes. This list may be not identical to dbIndexes property as it will
     * represent desired table state.
     *
     * @return IndexInterface[]
     */
    public function getIndexes();

    /**
     * Check if table has existed or declared foreign key references linked to specified column.
     *
     * @param string $column Column name.
     * @return bool
     */
    public function hasForeign($column);

    /**
     * Get all declared foreign keys. This list may be not identical to dbReferences property as it
     * will represent desired table state.
     *
     * @return ReferenceInterface[]
     */
    public function getForeigns();

    /**
     * Get list of table names should exist before saving current table schema. This list includes
     * all tables schema references to. Method can be used to sort multiple table schemas in order
     * they has to be created without violating constraints. Attention, resulted table list will
     * include table prefixes.
     *
     * @return array
     */
    public function getDependencies();
}