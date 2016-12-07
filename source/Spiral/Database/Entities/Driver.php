<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities;

use Spiral\Core\FactoryInterface;
use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\InsertQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\Schemas\AbstractTable;

/**
 * Driver abstraction is responsible for DBMS specific set of functions and used by Databases to
 * hide implementation specific functionality. Extends PDODriver and adds ability to create driver
 * specific query builders and schemas (basically operates like a factory).
 */
abstract class Driver extends PDODriver
{
    /**
     * Schema table class.
     */
    const SCHEMA_TABLE = '';

    /**
     * Commander used to execute commands. :).
     */
    const COMMANDER = '';

    /**
     * Query compiler class.
     */
    const QUERY_COMPILER = '';

    /**
     * Default datetime value.
     */
    const DEFAULT_DATETIME = '1970-01-01 00:00:00';

    /**
     * Default timestamp expression.
     */
    const TIMESTAMP_NOW = 'DRIVER_SPECIFIC_NOW_EXPRESSION';

    /**
     * Container is needed to construct instances of QueryCompiler.
     *
     * @invisible
     *
     * @var FactoryInterface
     */
    protected $factory = null;

    /**
     * @param string           $name
     * @param array            $connection
     * @param FactoryInterface $factory
     */
    public function __construct(string $name, array $connection, FactoryInterface $factory)
    {
        parent::__construct($name, $connection);

        $this->factory = $factory;
    }

    /**
     * Current timestamp expression value.
     *
     * @return string
     */
    public function nowExpression(): string
    {
        return static::TIMESTAMP_NOW;
    }

    /**
     * Check if table exists.
     *
     * @param string $name
     *
     * @return bool
     */
    abstract public function hasTable(string $name): bool;

    /**
     * Clean (truncate) specified driver table.
     *
     * @param string $table Table name with prefix included.
     */
    abstract public function truncateData(string $table);

    /**
     * Get every available table name as array.
     *
     * @return array
     */
    abstract public function tableNames(): array;

    /**
     * Get Driver specific AbstractTable implementation.
     *
     * @param string $table  Table name without prefix included.
     * @param string $prefix Database specific table prefix, this parameter is not required,
     *                       but if provided all
     *                       foreign keys will be created using it.
     *
     * @return AbstractTable
     */
    public function tableSchema($table, $prefix = ''): AbstractTable
    {
        return $this->factory->make(static::SCHEMA_TABLE, [
            'driver'    => $this,
            'name'      => $table,
            'prefix'    => $prefix,
            'commander' => $this->factory->make(static::COMMANDER, ['driver' => $this]),
        ]);
    }

    /**
     * Get instance of Driver specific QueryCompiler.
     *
     * @param string $prefix Database specific table prefix, used to quote table names and build
     *                       aliases.
     *
     * @return QueryCompiler
     */
    public function queryCompiler(string $prefix = ''): QueryCompiler
    {
        return $this->factory->make(static::QUERY_COMPILER, [
            'driver' => $this,
            'quoter' => new Quoter($this, $prefix),
        ]);
    }

    /**
     * Get InsertQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     *
     * @return InsertQuery
     */
    public function insertBuilder(Database $database, array $parameters = []): InsertQuery
    {
        return $this->factory->make(InsertQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix()),
            ] + $parameters);
    }

    /**
     * Get SelectQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     *
     * @return SelectQuery
     */
    public function selectBuilder(Database $database, array $parameters = []): SelectQuery
    {
        return $this->factory->make(SelectQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix()),
            ] + $parameters);
    }

    /**
     * Get DeleteQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     *
     * @return DeleteQuery
     */
    public function deleteBuilder(Database $database, array $parameters = []): DeleteQuery
    {
        return $this->factory->make(DeleteQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix()),
            ] + $parameters);
    }

    /**
     * Get UpdateQuery builder with driver specific query compiler.
     *
     * @param Database $database   Database instance builder should be associated to.
     * @param array    $parameters Initial builder parameters.
     *
     * @return UpdateQuery
     */
    public function updateBuilder(Database $database, array $parameters = []): UpdateQuery
    {
        return $this->factory->make(UpdateQuery::class, [
                'database' => $database,
                'compiler' => $this->queryCompiler($database->getPrefix()),
            ] + $parameters);
    }
}
