<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug\Traits;

use Interop\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\Debug\LogsInterface;

/**
 * On demand logger creation. Allows class to share same logger between instances.
 * 
 * @todo create logger using container intstance registration (see Container::registerInstance)
 */
trait LoggerTrait
{
    /**
     * @internal
     * @var LoggerInterface[]
     */
    private static $loggers = [];

    /**
     * Private and null.
     *
     * @internal
     * @var LoggerInterface|null
     */
    private $logger = null;

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Set class specific logger (associated with every instance).
     *
     * @param LoggerInterface $logger
     */
    public static function shareLogger(LoggerInterface $logger)
    {
        self::$loggers[static::class] = $logger;
    }

    /**
     * Get associated or create new instance of LoggerInterface.
     *
     * @return LoggerInterface
     */
    protected function logger()
    {
        if (!empty($this->logger)) {
            return $this->logger;
        }

        if (!empty(self::$loggers[static::class])) {
            return self::$loggers[static::class];
        }

        //We are using class name as log channel (name) by default
        return self::$loggers[static::class] = $this->createLogger();
    }

    /**
     * Create new instance of associated logger.
     *
     * @return LoggerInterface
     */
    protected function createLogger()
    {
        if (empty($container = $this->container()) || !$container->has(LogsInterface::class)) {
            return new NullLogger();
        }

        //We are using class name as log channel (name) by default
        return $container->get(LogsInterface::class)->getLogger(static::class);
    }

    /**
     * @return ContainerInterface
     */
    abstract protected function container();
}
