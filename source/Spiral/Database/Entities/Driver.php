<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities;

use Psr\Log\LoggerAwareInterface;
use Spiral\Database\Builders\DeleteQuery;
use Spiral\Database\Builders\InsertQuery;
use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Builders\UpdateQuery;
use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractIndex;
use Spiral\Database\Entities\Schemas\AbstractReference;
use Spiral\Database\Entities\Schemas\AbstractTable;

/**
 * Driver abstraction is responsible for DBMS specific set of functions and used by Databases to
 * hide implementation specific functionality. Extends PDODriver and adds ability to create driver
 * specific query builders and schemas.
 */
abstract class Driver extends PDODriver implements LoggerAwareInterface
{
    /**
     * Driver schemas.
     */
    const SCHEMA_TABLE     = '';
    const SCHEMA_COLUMN    = '';
    const SCHEMA_INDEX     = '';
    const SCHEMA_REFERENCE = '';

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
    public function timestampNow()
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
     * @param string $tablePrefix Database specific table prefix, this parameter is not required,
     *                            but if provided all
     *                            foreign keys will be created using it.
     * @return AbstractTable
     */
    public function tableSchema($table, $tablePrefix = '')
    {
        return $this->container->construct(static::SCHEMA_TABLE, [
            'driver'      => $this,
            'name'        => $table,
            'tablePrefix' => $tablePrefix
        ]);
    }

    /**
     * Get Driver specific AbstractColumn implementation.
     *
     * @param AbstractTable $table  Parent TableSchema.
     * @param string        $name   Column name.
     * @param mixed         $schema Driver specific column schema.
     * @return AbstractColumn
     */
    public function columnSchema(AbstractTable $table, $name, $schema = null)
    {
        return $this->container->construct(static::SCHEMA_COLUMN,
            compact('table', 'name', 'schema'));
    }

    /**
     * Get Driver specific AbstractIndex implementation.
     *
     * @param AbstractTable $table  Parent TableSchema.
     * @param string        $name   Index name.
     * @param mixed         $schema Driver specific index schema.
     * @return AbstractIndex
     */
    public function indexSchema(AbstractTable $table, $name, $schema = null)
    {
        return $this->container->construct(static::SCHEMA_INDEX,
            compact('table', 'name', 'schema'));
    }

    /**
     * Get Driver specific AbstractReference implementation.
     *
     * @param AbstractTable $table  Parent TableSchema.
     * @param string        $name   Constraint name.
     * @param mixed         $schema Driver specific foreign key schema.
     * @return AbstractReference
     */
    public function referenceSchema(AbstractTable $table, $name, $schema = null)
    {
        return $this->container->construct(static::SCHEMA_REFERENCE,
            compact('table', 'name', 'schema'));
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

    /**
     * Get instance of Driver specific QueryCompiler.
     *
     * @param string $tablePrefix Database specific table prefix, used to quote table names and
     *                            build aliases.
     * @return QueryCompiler
     */
    public function queryCompiler($tablePrefix = '')
    {
        return $this->container->construct(static::QUERY_COMPILER, [
            'driver'      => $this,
            'tablePrefix' => $tablePrefix
        ]);
    }
}