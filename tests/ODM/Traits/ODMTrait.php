<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Traits;

use Mockery as m;
use Spiral\Core\Container;
use Spiral\Core\MemoryInterface;
use Spiral\ODM\Configs\MutatorsConfig;
use Spiral\ODM\MongoManager;
use Spiral\ODM\ODM;
use Spiral\ODM\Schemas\SchemaBuilder;

trait ODMTrait
{
    /**
     * @return SchemaBuilder
     */
    protected function makeBuilder()
    {
        return new SchemaBuilder(m::mock(MongoManager::class));
    }

    /**
     * @return ODM
     */
    protected function makeODM()
    {
        $memory = m::mock(MemoryInterface::class);
        $memory->shouldReceive('loadData')->with(ODM::MEMORY)->andReturn([]);

        return new ODM(m::mock(MongoManager::class), $memory, new Container());
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
                'int'     => ['setter' => 'intval'],
                'float'   => ['setter' => 'floatval'],
                'string'  => ['setter' => 'strval'],
                'bool'    => ['setter' => 'boolval'],

                //Automatic casting of mongoID
                'MongoId' => ['setter' => [ODM::class, 'mongoID']],

                //'array'     => ['accessor' => ScalarArray::class],
                //'MongoDate' => ['accessor' => Accessors\MongoTimestamp::class],
                //'timestamp' => ['accessor' => Accessors\MongoTimestamp::class],
                /*{{mutators}}*/
            ],
            /*
             * Mutator aliases can be used to declare custom getter and setter filter methods.
             */
            'aliases'  => [
                'integer' => 'int',
                'long'    => 'int',
                'text'    => 'string',

                /*{{mutators.aliases}}*/
            ]
        ]);
    }
}