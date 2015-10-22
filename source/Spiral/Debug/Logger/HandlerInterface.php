<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug\Logger;

/**
 * Handlers used to listen to log messages and store them accordingly.
 */
interface HandlerInterface
{
    /**
     * HandlerInterface should only accept options from debug. Use depends method for additional
     * classes.
     *
     * @param array $options
     */
    public function __construct(array $options);

    /**
     * Handle log message.
     *
     * @param int    $level
     * @param string $message
     * @param array  $context
     */
    public function __invoke($level, $message, array $context = []);
}