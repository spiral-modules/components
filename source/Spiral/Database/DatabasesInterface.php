<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database;

use Spiral\Database\Exceptions\DatabaseException;

/**
 * Databases factory/manager.
 */
interface DatabasesInterface
{
    /**
     * Create specified or select default instance of DatabaseInterface.
     *
     * @param string $database Database alias.
     * @return DatabaseInterface
     * @throws DatabaseException
     */
    public function database($database = null);
}