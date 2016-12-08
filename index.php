<?php
/**
 * Spiral Framework, SpiralScout LLC.
 *
 * @author    Anton Titov (Wolfy-J)
 */
define('SPIRAL_INITIAL_TIME', microtime(true));

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);
mb_internal_encoding('UTF-8');

//Composer
require 'vendor/autoload.php';

$container = new \Spiral\Core\Container();

$driver = new \Spiral\Database\Drivers\SQLite\SQLiteDriver(
    'sqlite',
    [
        'connection' => 'sqlite:runtime.db',
        'username'   => 'sqlite',
    ],
    $container
);

$schema = $driver->tableSchema('COMPANY');

foreach ($schema->getColumns() as $column) {
    print_R($column->getName() . " ");
    print_R($column->abstractType() . "\n ");

}

print_r($schema->getPrimaryKeys());