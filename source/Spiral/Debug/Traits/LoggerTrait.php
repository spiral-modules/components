<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */

namespace Spiral\Debug\Traits;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\Core\ContainerInterface;

/**
 * On demand logger creation. Allows class to share same logger between instances.
 */
trait LoggerTrait
{
    /**
     * Loggers associated to classes not instances.
     *
     * @var LoggerInterface[]
     */
    private static $loggers = [];

    /**
     * The logger instance.
     *
     * @var LoggerInterface
     */
    private $logger = null;

    /**
     * @return ContainerInterface
     */
    abstract public function container();

    /**
     * Sets a logger. Static loggers has less priority over loggers associated with specific object.
     *
     * @param LoggerInterface $logger
     * @param bool            $static Associate logger to set of classes, not one object.
     * @return $this
     */
    public function setLogger(LoggerInterface $logger, $static = false)
    {
        if ($static) {
            self::$loggers[static::class] = $logger;
        } else {
            $this->logger = $logger;
        }

        return $this;
    }

    /**
     * Get associated or create new instance of LoggerInterface.
     *
     * @return LoggerInterface
     */
    public function logger()
    {
        if (!empty($this->logger)) {
            return $this->logger;
        }

        if (!empty(self::$loggers[static::class])) {
            return self::$loggers[static::class];
        }

        if (empty($container = $this->container()) || !$container->has(LoggerInterface::class)) {
            //That's easy
            return new NullLogger();
        }

        return self::$loggers[static::class] = $container->construct(LoggerInterface::class, [
            'name' => static::class
        ]);
    }
}