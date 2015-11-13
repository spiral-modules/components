<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug;

use Monolog\Logger;
use Spiral\Core\Container\SingletonInterface;

/**
 * DebugLogger used as global system logger to handle errors, debug messages and etc.
 */
class DebugLogger extends Logger implements SingletonInterface
{
    /**
     * This logger is global for whole application.
     */
    const SINGLETON = self::class;

    /**
     * @param Debugger $debugger
     */
    public function __construct(Debugger $debugger)
    {
        parent::__construct(static::class, $debugger->getHandlers(static::class));
    }
}