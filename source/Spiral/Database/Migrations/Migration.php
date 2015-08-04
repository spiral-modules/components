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
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Entities\Table;

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
     * @var Database
     */
    protected $database = null;

    /**
     * {@inheritdoc}
     */
    public function setDatabase(Database $database)
    {
        $this->database = $database;
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
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get Table abstraction from associated database.
     *
     * @param string $name Table name without prefix.
     * @return Table
     */
    public function table($name)
    {
        return $this->database->table($name);
    }

    /**
     * Get instance of TableSchema associated with specific table name and migration database.
     *
     * @param string $table Table name without prefix.
     * @return AbstractTable
     */
    public function schema($table)
    {
        return $this->table($table)->schema();
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