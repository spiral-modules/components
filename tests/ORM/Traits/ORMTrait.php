<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Traits;

use Spiral\ORM\Configs\MutatorsConfig;

trait ORMTrait
{
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

            'aliases' => [
            ]
        ]);
    }
}