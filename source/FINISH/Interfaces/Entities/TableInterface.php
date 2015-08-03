<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces\Entities;

use Spiral\Database\Interfaces\Builders\DeleteBuilderInterface;
use Spiral\Database\Interfaces\Builders\SelectBuilderInterface;
use Spiral\Database\Interfaces\Builders\UpdateBuilderInterface;

interface TableInterface extends \Countable
{
    /**
     * Table name without included prefix.
     *
     * @return string
     */
    public function getName();

    /**
     * Get schema for specified table. TableSchema contains information about all table columns,
     * indexes and foreign keys. Schema can also be used to manipulate table structure.
     *
     * @return \Spiral\Database\Interfaces\Entities\Schemas\TableInterface
     */
    public function schema();

    /**
     * Truncate (clean) current table.
     */
    public function truncate();

    /**
     * Perform single rowset insertion into table. Method will return lastInsertID on success.
     *
     * Example:
     * $table->insert(["name" => "Wolfy J"])
     *
     * @param array $rowset Associated array (key=>value).
     * @return mixed
     */
    public function insert(array $rowset = []);

    /**
     * Perform batch insert into table, every rowset should have identical amount of values matched
     * with column names provided in first argument. Method will return lastInsertID on success.
     *
     * Example:
     * $table->insert(["name", "balance"], array(["Bob", 10], ["Jack", 20]))
     *
     * @param array $columns Array of columns.
     * @param array $rowsets Array of rowsets.
     * @return mixed
     */
    public function batchInsert(array $columns = [], array $rowsets = []);

    /**
     * Get SelectQuery builder with pre-populated from tables.
     *
     * @param string $columns
     * @return SelectBuilderInterface
     */
    public function select($columns = '*');

    /**
     * Get UpdateQuery builder with pre-populated table name and set of columns to update. Columns
     * can be scalar values, Parameter objects or even SQLFragments. Call ->run() to perform query.
     *
     * @param array $values Initial set of columns associated with values.
     * @param array $where  Initial set of where rules specified as array.
     * @return UpdateBuilderInterface
     */
    public function update(array $values = [], array $where = []);

    /**
     * Get DeleteQuery builder with pre-populated table name. This is NOT table delete method, use
     * schema()->drop() for this purposes. If you want to remove all records from table use
     * Table->truncate() method. Call ->run() to perform query.
     *
     * @param array $where Initial set of where rules specified as array.
     * @return DeleteBuilderInterface
     */
    public function delete(array $where = []);
}