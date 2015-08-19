<?php
/**
 * Configuration of debug component and related classes:
 * - global log populated by every instance of spiral Logger and used in exception snapshots or
 *   profiler
 * - list of logger channels associated with their message handlers
 * - configuration for default debug snapshot implementation, including reporting directory and view
 *   to be used for exceptions
 */
use Spiral\Debug\Debugger;
use Spiral\Debug\Logger;
use Spiral\Debug\Logger\Handlers\FileHandler;
use Spiral\Http\HttpDispatcher;

return [
    'globalLogging' => [
        'enabled' => true,
        'maxSize' => 1000
    ],
    'loggers'       => [
        Debugger::class       => [
            Logger::ERROR => [
                'class'    => FileHandler::class,
                'filename' => 'logs/errors.log'
            ],
            Logger::ALL   => [
                'class'    => FileHandler::class,
                'filename' => 'logs/debug.log'
            ]
        ],
        HttpDispatcher::class => [
            Logger::WARNING => [
                'class'    => FileHandler::class,
                'filename' => 'logs/httpErrors.log'
            ]
        ]
    ],
    'snapshots'     => [
        'view'      => 'spiral:exception',
        'reporting' => [
            'enabled'      => false,
            'maxSnapshots' => 20,
            'directory'    => 'logs/snapshots',
            'filename'     => '{date}-{exception}.html',
            'dateFormat'   => 'd.m.Y-Hi.s',
        ]
    ]
];