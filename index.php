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

$driver = new \Spiral\Database\Drivers\MySQL\MySQLDriver(
    'mysql',
    [
        'connection' => 'mysql:host=localhost;dbname=earthquake',
        'username'   => 'root',
        'password'   => ''
    ],
    $container
);

$schema = $driver->tableSchema('accounts');

foreach ($schema->getColumns() as $column) {
    print_R($column->getName() . " ");
    print_R($column->abstractType() . "\n ");
}


echo "----------\n";
foreach ($schema->getIndexes() as $index) {
    print_r($index->getName());
    print_r($index->getColumns() . "\n");
}

echo "----------\n";

foreach ($schema->getForeigns() as $f) {
    print_r($f->getName() . ' ');
    print_r($f->getColumn() . ' ');
    print_r($f->getForeignTable() . ' ');
    print_r($f->getForeignKey() . "\n");
}

print_r($schema->getPrimaryKeys());
