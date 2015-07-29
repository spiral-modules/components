<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug\Logger;

interface HandlerInterface
{
    /**
     * HandlerInterface should only accept options from debug, due it's going to be created using
     * container you can declare any additional dependencies you want.
     *
     * @param array $options
     */
    public function __construct(array $options);

    /**
     * Handle log message.
     *
     * @param int    $level   Log message level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    public function __invoke($level, $message, array $context);
}