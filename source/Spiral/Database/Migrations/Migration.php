<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Migrations;

use Spiral\Core\Component;
use Spiral\Database\DatabaseManager;
use Spiral\Database\DatabasesInterface;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Entities\Table;
use Spiral\Database\Exceptions\MigrationException;
use Spiral\Database\Exceptions\SchemaException;

/**
 * Default implementation of MigrationInterface with simplified access to table schemas.
 */
abstract class Migration extends Component implements MigrationInterface
{
    /**
     * @var StatusInterface|null
     */
    private $status = null;

    /**
     * @var DatabasesInterface
     */
    protected $databases = null;

    /**
     * Provide instance of database provider to migration.
     *
     * @param DatabaseManager $databases
     */
    public function setDBAL(DatabaseManager $databases)
    {
        if (!$databases instanceof DatabaseManager) {
            throw new MigrationException(
                "Spiral Migrations expect to work DatabaseProvider instance."
            );
        }

        $this->databases = $databases;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(StatusInterface $status)
    {
        $this->status = $status;
    }

    /**
     * {@inheritdoc}
     */
    public function status()
    {
        return $this->status;
    }

    /**
     * Get Table abstraction from associated database.
     *
     * @param string      $name     Table name without prefix.
     * @param string|null $database Database to used, keep unfilled for default database.
     * @return Table
     */
    public function table($name, $database = null)
    {
        return $this->databases->db($database)->table($name);
    }

    /**
     * Get instance of TableSchema associated with specific table name and migration database.
     *
     * @param string      $table    Table name without prefix.
     * @param string|null $database Database to used, keep unfilled for default database.
     * @return AbstractTable
     */
    public function schema($table, $database = null)
    {
        return $this->table($table, $database)->schema();
    }

    /**
     * Create items in table schema or thrown and exception. No altering allowed.
     *
     * @param string   $table
     * @param callable $creator
     * @throws SchemaException
     */
    public function create($table, callable $creator)
    {
        $this->schema($table)->create($creator);
    }

    /**
     * Alter items in table schema or thrown and exception. No creations allowed.
     *
     * @param string   $table
     * @param callable $creator
     * @throws SchemaException
     */
    public function alter($table, callable $creator)
    {
        $this->schema($table)->alter($creator);
    }

    /**
     * {@inheritdoc}
     */
    abstract public function up();

    /**
     * {@inheritdoc}
     */
    abstract public function down();
}