<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Traits;

use Spiral\ODM\Configs\MutatorsConfig;
use Spiral\ODM\MongoManager;
use Spiral\ODM\ODM;
use Spiral\ODM\Schemas\SchemaBuilder;
use Mockery as m;

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