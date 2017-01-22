<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Migrations;

use Spiral\Database\Entities\Database;
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
    private $state = null;

    /**
     * @var CapsuleInterface
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
        $migration->state = $state;

        return $migration;
    }

    /**
     * {@inheritdoc}
     */
    public function getState(): State
    {
        if (empty($this->state)) {
            throw new MigrationException("Unable to get migration state, no state are set");
        }

        return $this->state;
    }

    /**
     * @param string $database
     *
     * @return Database
     */
    protected function database(string $database = null): Database
    {
        if (empty($this->capsule)) {
            throw new MigrationException("Unable to get database, no capsule are set");
        }

        return $this->capsule->getDatabase($database);
    }

    /**
     * Get table schema builder (blueprint).
     *
     * @param string      $table
     * @param string|null $database
     *
     * @return TableBlueprint
     */
    protected function table(string $table, string $database = null): TableBlueprint
    {
        if (empty($this->capsule)) {
            throw new MigrationException("Unable to get table blueprint, no capsule are set");
        }

        return new TableBlueprint($this->capsule, $table, $database);
    }
}