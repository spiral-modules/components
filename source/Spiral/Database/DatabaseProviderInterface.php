<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database;

use Spiral\Database\Exceptions\DatabaseException;

interface DatabaseProviderInterface
{
    /**
     * Create specified or select default instance of DatabaseInterface.
     *
     * @param string $database
     * @param array  $config Custom db configuration.
     * @return DatabaseInterface
     * @throws DatabaseException
     */
    public function db($database = null, array $config = []);
}