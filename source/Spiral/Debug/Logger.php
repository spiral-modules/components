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

class Logger extends AbstractLogger
{
    /**
     * Helper constant to associate all log levels with one filename.
     */
    const ALL = 'all';

    /**
     * Default logging name (channel).
     */
    const DEFAULT_NAME = 'debug';

    /**
     * Message parts (stored in static log container).
     */
    const MESSAGE_CHANNEL   = 0;
    const MESSAGE_TIMESTAMP = 1;
    const MESSAGE_LEVEL     = 2;
    const MESSAGE_BODY      = 3;
    const MESSAGE_CONTEXT   = 4;

    /**
     * If enabled all debug messages will be additionally collected in Logger::$logMessages array for
     * future analysis. Only messages from current script session and recorded after option got
     * enabled will be collected.
     *
     * @var bool
     */
    private static $memoryLogging = true;

    /**
     * Log messages collected during application runtime. Messages will be displayed in exception
     * snapshot or can be retrieved by profiler module, memory logging disabled by CLI dispatched in
     * console environment.
     *
     * @var array
     */
    protected static $logMessages = [];

    /**
     * Logging container name, usually defined by component alias or class name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * List of log handlers associated with their log levels.
     *
     * @var callable[]
     */
    protected $handlers = [];

    /**
     * New logger instance, usually attached to component or set of models, by model class name or
     * alias. PSR-3 compatible and can be replaced with foreign implementation.
     *
     * @param string   $name
     * @param Debugger $debugger Debugger is required to supply config.
     */
    public function __construct($name = self::DEFAULT_NAME, Debugger $debugger = null)
    {
        $this->name = $name;

        //Configuring handlers
        !empty($debugger) && $debugger->configureLogger($this);
    }

    /**
     * Get logger name (channel).
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Add log handler to output all log messages with specified log level, if log level specified
     * as Logger::ALL_MESSAGES every message will processed thought this handler, however if there
     * is more specific log level handler - it will be used instead of "all" handler.
     *
     * @param string   $level   Log level, use Logger::allMessages to log all messages.
     * @param callable $handler Handler.
     * @return $this
     */
    public function setHandler($level, callable $handler)
    {
        $this->handlers[$level] = $handler;

        return $this;
    }

    /**
     * Logs with specified level. If logger has defined file handlers message will be automatically
     * written to file.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return $this
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
     * If enabled all debug messages will be additionally collected in $logMessages array for future
     * analysis. Only messages from current script session and recorded after option got enabled will
     * be collection in logMessages array.
     *
     * @param bool $enabled
     * @return bool
     */
    public static function memoryLogging($enabled = true)
    {
        $currentValue = self::$memoryLogging;
        self::$memoryLogging = $enabled;

        return $currentValue;
    }

    /**
     * Get all recorded log messages.
     *
     * @return array
     */
    public static function logMessages()
    {
        return self::$logMessages;
    }
}