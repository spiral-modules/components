<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Basic spiral implementation of PSR logger, allows custom handlers for every log level.
 */
class Logger extends AbstractLogger
{
    /**
     * Default logging name (channel).
     */
    const DEFAULT_NAME = 'debug';

    /**
     * Helper constant to associate all log levels with one filename.
     */
    const ALL = 'all';

    /**
     * Copy of LogLevels.
     */
    const EMERGENCY = LogLevel::EMERGENCY;
    const ALERT     = LogLevel::ALERT;
    const CRITICAL  = LogLevel::CRITICAL;
    const ERROR     = LogLevel::ERROR;
    const WARNING   = LogLevel::WARNING;
    const NOTICE    = LogLevel::NOTICE;
    const INFO      = LogLevel::INFO;
    const DEBUG     = LogLevel::DEBUG;

    /**
     * Message parts (stored in static log container).
     */
    const MESSAGE_CHANNEL   = 0;
    const MESSAGE_TIMESTAMP = 1;
    const MESSAGE_LEVEL     = 2;
    const MESSAGE_BODY      = 3;
    const MESSAGE_CONTEXT   = 4;

    /**
     * When memory logging is enabled every log raised by logger will be stored in global array.
     *
     * @var bool
     */
    private static $memoryLogging = false;

    /**
     * Globally recorded messages.
     *
     * @var array
     */
    protected static $logMessages = [];

    /**
     * Logging container name, usually defined by component alias or class name.
     *
     * @var string
     */
    private $name = '';

    /**
     * List of log handlers associated with their log levels.
     *
     * @var callable[]
     */
    protected $handlers = [];

    /**
     * @param string   $name
     * @param Debugger $debugger Used to automatically configure handlers.
     */
    public function __construct($name = self::DEFAULT_NAME, Debugger $debugger = null)
    {
        $this->name = $name;

        //Configuring handlers
        !empty($debugger) && $debugger->configureLogger($this);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Associated log handlers with specific log level or all levels (ALL constant).
     *
     * @param string   $level
     * @param callable $handler
     * @return $this
     */
    public function setHandler($level, callable $handler)
    {
        $this->handlers[$level] = $handler;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = [])
    {
        $payload = [
            self::MESSAGE_CHANNEL   => $this->name,
            self::MESSAGE_TIMESTAMP => microtime(true),
            self::MESSAGE_LEVEL     => $level,
            self::MESSAGE_BODY      => \Spiral\interpolate($message, $context),
            self::MESSAGE_CONTEXT   => $context
        ];

        if (self::$memoryLogging)
        {
            self::$logMessages[] = $payload;
        }

        //We don't need this information for log handlers
        unset($payload[self::MESSAGE_CHANNEL], $payload[self::MESSAGE_TIMESTAMP]);

        if (isset($this->handlers[$level]))
        {
            call_user_func_array($this->handlers[$level], $payload);
        }
        elseif (isset($this->handlers[self::ALL]))
        {
            call_user_func_array($this->handlers[self::ALL], $payload);
        }

        return $this;
    }

    /**
     * When memory logging is enabled every log raised by logger will be stored in global array.
     *
     * @param bool $enabled
     */
    public static function memoryLogging($enabled = true)
    {
        self::$memoryLogging = $enabled;
    }

    /**
     * Get all recorded log messages. MemoryLogging has to be enabled.
     *
     * @return array
     */
    public static function logMessages()
    {
        return self::$logMessages;
    }
}