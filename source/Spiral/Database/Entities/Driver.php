<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities;

use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\InsertQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\Entities\Schemas\AbstractTable;

/**
 * Driver abstraction is responsible for DBMS specific set of functions and used by Databases to
 * hide implementation specific functionality. Extends PDODriver and adds ability to create driver
 * specific query builders and schemas (basically operates like a factory).
 */
abstract class Driver extends PDODriver
{
    /**
     * Driver schemas.
     */
    const SCHEMA_TABLE     = '';

    /**
     * Commander used to execute commands. :)
     */
    const COMMANDER = '';

    /**
     * Default datetime value.
     */
    const DEFAULT_DATETIME = '1970-01-01 00:00:00';

    /**
     * Default timestamp expression.
     */
    const TIMESTAMP_NOW = 'DRIVER_SPECIFIC_NOW_EXPRESSION';

    /**
     * Current timestamp expression value.
     *
     * @return string
     */
    public function nowExpression()
    {
        return static::TIMESTAMP_NOW;
    }

    /**
     * Clean (truncate) specified driver table.
     *
     * @param string $table Table name with prefix included.
     */
    public function truncate($table)
    {
        $this->statement("TRUNCATE TABLE {$this->identifier($table)}");
    }

    /**
     * Check if table exists.
     *
     * @param string $name
     * @return bool
     */
    abstract public function hasTable($name);

    /**
     * Get every available table name as array.
     *
     * @return array
     */
    abstract public function tableNames();

    /**
     * Get Driver specific AbstractTable implementation.
     *
     * @param string $table       Table name without prefix included.
     * @param string $prefix      Database specific table prefix, this parameter is not required,
     *                            but if provided all
     *                            foreign keys will be created using it.
     * @return AbstractTable
     */
    public function tableSchema($table, $prefix = '')
    {
        return $this->container->construct(static::SCHEMA_TABLE, [
            'driver'    => $this,
            'name'      => $table,
            'prefix'    => $prefix,
            'commander' => $this->container->construct(static::COMMANDER, ['driver' => $this])
        ]);
    }

    /**
     * Get InsertQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     * @return InsertQuery
     */
    public function insertBuilder(Database $database, array $parameters = [])
    {
        return $this->container->construct(InsertQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix())
            ] + $parameters);
    }

    /**
     * Get SelectQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     * @return SelectQuery
     */
    public function selectBuilder(Database $database, array $parameters = [])
    {
        return $this->container->construct(SelectQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix())
            ] + $parameters);
    }

    /**
     * Get DeleteQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     * @return DeleteQuery
     */
    public function deleteBuilder(Database $database, array $parameters = [])
    {
        return $this->container->construct(DeleteQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix())
            ] + $parameters);
    }

    /**
     * Get UpdateQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     * @return UpdateQuery
     */
    public function updateBuilder(Database $database, array $parameters = [])
    {
        return $this->container->construct(UpdateQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix())
            ] + $parameters);
    }
}