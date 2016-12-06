<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Migrations;

use Spiral\Migrations\Migration\Meta;

interface MigrationInterface
{
    /**
     * @param MigrationCapsule $pipeline
     */
    public function setContext(MigrationCapsule $pipeline);

    /**
     * @param Meta $state
     */
    public function setMeta(Meta $state);

    /**
     * @return Meta|null
     */
    public function getMeta();

    /**
     * Up migration.
     */
    public function up();

    /**
     * Rollback migration.
     */
    public function down();
}