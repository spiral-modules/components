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

trait LoggerTrait
{
    /**
     * We can extend this trait.
     */
    use LoggerAwareTrait;

    /**
     * Static logger associated to every class instance by it's class name.
     *
     * @var LoggerInterface[]
     */
    private static $loggers = [];

    /**
     * Global container access is required in some cases. Method should be declared statically.
     *
     * @return ContainerInterface
     */
    abstract public function getContainer();

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     * @param bool            $static If true logger will applied to every class instance.
     */
    public function setLogger(LoggerInterface $logger, $static = false)
    {
        if (!$static)
        {
            $this->logger = $logger;

            return;
        }

        self::$loggers[static::class] = $logger;
    }

    /**
     * Getting logger instance. By default logger will receive variable with class name.
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
            //Static logger is mounted
            return self::$loggers[static::class];
        }

        $container = self::getContainer();
        if (empty($container) || !$container->hasBinding(LoggerInterface::class))
        {
            //That's easy
            return new NullLogger();
        }

        return self::$loggers[static::class] = $container->get(LoggerInterface::class, [
            'name' => static::class
        ]);
    }
}