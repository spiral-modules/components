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
use Spiral\Database\Database;
use Spiral\Database\Schemas\AbstractTable;
use Spiral\Database\Table;

abstract class Migration extends Component implements MigrationInterface
{
    /**
     * Target database instance.
     *
     * @var Database
     */
    protected $database = null;

    /**
     * Migration status.
     *
     * @var StatusInterface|null
     */
    protected $status = null;

    /**
     * Configuring migration. This method will be automatically called after migration created and
     * used to resolve target database.
     *
     * @param Database $database
     */
    public function setDatabase(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Migration status should be supplied by MigratorInterface and describe migration state.
     *
     * @param StatusInterface $status
     */
    public function setStatus(StatusInterface $status)
    {
        $this->status = $status;
    }

    /**
     * Migration status are filled by MigratorInterface and describes status of migration. Can be
     * empty for new migrations.
     *
     * @return StatusInterface|null
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get Table instance associated with target database.
     *
     * @param string $name Table name without prefix.
     * @return Table
     */
    public function table($name)
    {
        return $this->database->table($name);
    }

    /**
     * Get table schema from associated database, schema can be used for different operations, such
     * as creation, updating, dropping and etc.
     *
     * @param string $table Table name without prefix.
     * @return AbstractTable
     */
    public function schema($table)
    {
        return $this->table($table)->schema();
    }

    /**
     * Executing migration.
     */
    abstract public function up();

    /**
     * Dropping (rollback) migration.
     */
    abstract public function down();
}