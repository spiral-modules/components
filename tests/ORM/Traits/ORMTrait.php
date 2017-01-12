<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Traits;

use Spiral\Core\Container;
use Spiral\Database\Configs\DatabasesConfig;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Drivers\SQLite\SQLiteDriver;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ORM\Configs\MutatorsConfig;
use Spiral\ORM\Configs\RelationsConfig;
use Spiral\ORM\Entities\Loaders;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas;

trait ORMTrait
{
    /**
     * @return Schemas\SchemaBuilder
     */
    protected function makeBuilder(DatabaseManager $mananer = null)
    {
        return new Schemas\SchemaBuilder(
            $mananer ?? $this->databaseManager(),
            new Schemas\RelationBuilder($this->relationsConfig(), new Container())
        );
    }

    /**
     * @param string $class
     *
     * @return Schemas\RecordSchema
     */
    protected function makeSchema(string $class): Schemas\RecordSchema
    {
        return new Schemas\RecordSchema(new ReflectionEntity($class), $this->mutatorsConfig());
    }

    /**
     * Default SQLite database.
     *
     * @return DatabaseManager
     */
    protected function databaseManager(): DatabaseManager
    {
        //todo: clean before giving to schema
        return new DatabaseManager(new DatabasesConfig([
            'default'     => 'default',
            'aliases'     => [],
            'databases'   => [
                'default' => ['connection' => 'runtime', 'tablePrefix' => ''],
            ],
            'connections' => [
                'runtime' => [
                    'driver'     => SQLiteDriver::class,
                    'connection' => 'sqlite::memory:',
                    'username'   => 'sqlite',
                ],
            ]
        ]));
    }

    /**
     * @return MutatorsConfig
     */
    protected function mutatorsConfig()
    {
        return new MutatorsConfig([
            /*
             * Set of mutators to be applied for specific field types.
             */
            'mutators' => [
                'php:int'    => ['setter' => 'intval', 'getter' => 'intval'],
                'php:float'  => ['setter' => 'floatval', 'getter' => 'floatval'],
                'php:string' => ['setter' => 'strval'],
                'php:bool'   => ['setter' => 'boolval', 'getter' => 'boolval'],
            ],

            'aliases' => []
        ]);
    }

    /**
     * @return RelationsConfig
     */
    protected function relationsConfig()
    {
        return new RelationsConfig([
            Record::BELONGS_TO   => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\BelongsToSchema::class,
                RelationsConfig::LOADER_CLASS => Loaders\BelongsToLoader::class
            ],
            Record::HAS_ONE      => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\HasOneSchema::class,
                RelationsConfig::LOADER_CLASS => Loaders\HasOneLoader::class

            ],
            Record::HAS_MANY     => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\HasManySchema::class,
                RelationsConfig::LOADER_CLASS => Loaders\HasManyLoader::class
            ],
            Record::MANY_TO_MANY => [
                RelationsConfig::SCHEMA_CLASS => Schemas\Relations\ManyToManySchema::class,
                RelationsConfig::LOADER_CLASS => Loaders\ManyToManyLoader::class
            ],
        ]);
    }
}