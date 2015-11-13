<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug;

use Psr\Log\LoggerInterface;

/**
 * Has to provide configured instance of log.
 */
interface LogsInterface
{
    /**
     * Get pre-configured logger instance.
     *
     * @param string $name
     * @return LoggerInterface
     */
    public function createLogger($name);
}