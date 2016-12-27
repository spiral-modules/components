<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Entities;

use MongoDB\Database;
use Spiral\Core\Container\InjectableInterface;
use Spiral\ODM\MongoManager;

/**
 * Extends default driver class in order to prevent name conflicts with DBAL Database and enable
 * auto injections.
 */
class MongoDatabase extends Database implements InjectableInterface
{
    //Tell IoC that by default MongoDatabase must be supplied by MongoManager component
    const INJECTOR = MongoManager::class;
}