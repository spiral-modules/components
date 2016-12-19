<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Migrations;

use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Table;
use Spiral\Migrations\Exceptions\MigrationException;
use Spiral\Migrations\Migration\State;

/**
 * Simple migration class with shortcut for database and blueprint instances.
 */
abstract class Migration implements MigrationInterface
{
    /**
     * @var State|null
     */
    private $status = null;

    /**
     * @var MigrationCapsule
     */
    private $capsule = null;

    /**
     * {@inheritdoc}
     */
    public function withCapsule(CapsuleInterface $capsule): MigrationInterface
    {
        $migration = clone $this;
        $migration->capsule = $capsule;

        return $migration;
    }

    /**
     * {@inheritdoc}
     */
    public function withState(State $state): MigrationInterface
    {
        $migration = clone $this;
        $migration->status = $state;

        return $migration;
    }

    /**
     * {@inheritdoc}
     */
    public function getState()
    {
        return $this->status;
    }

    /**
     * @param string $database
     *
     * @return Database
     */
    public function database(string $database = null): Database
    {
        if (!empty($this->capsule)) {
            throw new MigrationException("Unable to get database, no capsule are set");
        }

        return $this->capsule->getDatabase($database);
    }

    /**
     * @param string      $table
     * @param string|null $database
     *
     * @return Table
     */
    public function table(string $table, string $database = null): Table
    {
        if (!empty($this->capsule)) {
            throw new MigrationException("Unable to get table, no capsule are set");
        }

        return $this->capsule->getDatabase($database)->table($table);
    }


    /**
     * @param string      $table
     * @param string|null $database
     *
     * @return TableBlueprint
     */
    public function blueprint(string $table, string $database = null): TableBlueprint
    {
        if (!empty($this->capsule)) {
            throw new MigrationException("Unable to get table blueprint, no capsule are set");
        }

        return new TableBlueprint($this->capsule, $database, $table);
    }
}