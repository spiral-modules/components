<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Migrations;

use Spiral\Database\Entities\Database;
use Spiral\Migrations\Migration\Meta;

/**
 * Simple migration class with shortcut for database and blueprint instances.
 */
abstract class Migration implements MigrationInterface
{
    /**
     * @var Meta|null
     */
    private $status = null;

    /**
     * @var MigrationCapsule
     */
    private $context = null;

    /**
     * @param MigrationCapsule $context
     */
    public function setContext(MigrationCapsule $context)
    {
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function setMeta(Meta $state)
    {
        $this->status = $state;
    }

    /**
     * {@inheritdoc}
     */
    public function getMeta()
    {
        return $this->status;
    }

    /**
     * @param string $database
     *
     * @return Database
     */
    public function getDatabase($database = null)
    {
        return $this->context->getDatabase($database);
    }

    /**
     * @param string      $table
     * @param string|null $database
     *
     * @return TableBlueprint
     */
    public function getTable($table, $database = null)
    {
        return new TableBlueprint($this->context, $database, $table);
    }
}