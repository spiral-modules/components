<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */

namespace Spiral\Debug\Traits;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\Core\ContainerInterface;

/**
 * On demand logger creation. Allows class to share same logger between instances.
 */
trait LoggerTrait
{
    /**
     * To incorporate parent functionality.
     */
    use LoggerAwareTrait;

    /**
     * Loggers associated to classes not instances.
     *
     * @var LoggerInterface[]
     */
    private static $loggers = [];

    /**
     * @return ContainerInterface
     */
    abstract public function container();

    /**
     * Sets logger for every class instance, instance based loggers will be in priority compared to
     * global logger.
     *
     * @param LoggerInterface $logger
     */
    public function setGlobalLogger(LoggerInterface $logger)
    {
        self::$loggers[static::class] = $logger;
    }

    /**
     * Get associated or create new instance of LoggerInterface.
     *
     * @return LoggerInterface
     */
    public function logger()
    {
        if (!empty($this->logger))
        {
            return $this->logger;
        }

        if (!empty(self::$loggers[static::class]))
        {
            return self::$loggers[static::class];
        }

        if (empty($container = $this->container()) || !$container->hasBinding(LoggerInterface::class))
        {
            //That's easy
            return new NullLogger();
        }

        return self::$loggers[static::class] = $container->get(LoggerInterface::class, [
            'name' => static::class
        ]);
    }
}