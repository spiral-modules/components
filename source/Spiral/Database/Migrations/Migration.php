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
use Spiral\Core\ContainerInterface;
use Spiral\Database\DatabaseManager;
use Spiral\Database\DatabasesInterface;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Entities\Table;
use Spiral\Database\Exceptions\SchemaException;

/**
 * Default implementation of MigrationInterface with simplified access to table schemas.
 */
abstract class Migration extends Component implements MigrationInterface
{
    /**
     * @var StateInterface|null
     */
    private $status = null;

    /**
     * @var DatabasesInterface
     */
    protected $databases = null;

    /**
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * @param DatabaseManager    $databases
     * @param ContainerInterface $container
     */
    public function __construct(DatabaseManager $databases, ContainerInterface $container)
    {
        $this->databases = $databases;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function setState(StateInterface $status)
    {
        $this->status = $status;
    }

    /**
     * {@inheritdoc}
     */
    public function getState()
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function up();

    /**
     * {@inheritdoc}
     */
    abstract public function down();

    /**
     * Get Table abstraction from associated database.
     *
     * @param string      $name     Table name without prefix.
     * @param string|null $database Database to used, keep unfilled for default database.
     * @return Table
     */
    protected function table($name, $database = null)
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
    protected function schema($table, $database = null)
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
    protected function create($table, callable $creator)
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
    protected function alter($table, callable $creator)
    {
        $this->schema($table)->alter($creator);
    }
}