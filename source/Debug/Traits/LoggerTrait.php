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
use Spiral\Debug\Debugger;

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
     * Global container access is required in some cases.
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
        if ($static)
        {
            self::$loggers[static::class] = $logger;
        }
        else
        {
            $this->logger = $logger;
        }
    }

    /**
     * Getting logger instance. If no logger associated system will try to fetch it using Debugger
     * component, but only in case if global container is mounted.
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

        if (!empty(self::getContainer()))
        {
            return self::$loggers[static::class] = Debugger::getInstance(
                self::getContainer()
            )->createLogger(static::class);
        }

        return new NullLogger();
    }
}