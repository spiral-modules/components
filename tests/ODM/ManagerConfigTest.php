<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Spiral\ODM\Configs\MongoConfig;

class ManagerConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultDatabase()
    {
        $config = new MongoConfig([
            'default' => 'database-1'
        ]);

        $this->assertSame('database-1', $config->defaultDatabase());
    }

    public function testHasDatabase()
    {
        $config = new MongoConfig([
            'default'   => 'database-1',
            'databases' => [
                'test'  => [],
                'test2' => [],
            ]
        ]);

        $this->assertTrue($config->hasDatabase('test'));
        $this->assertTrue($config->hasDatabase('test2'));
        $this->assertFalse($config->hasDatabase('database-1'));
    }

    public function testDatabaseNames()
    {
        $config = new MongoConfig([
            'default'   => 'database-1',
            'databases' => [
                'test'  => [],
                'test2' => [],
            ]
        ]);

        $this->assertSame(['test', 'test2'], $config->databaseNames());
    }

    public function testAliases()
    {
        $config = new MongoConfig([
            'default'   => 'database-1',
            'aliases'   => [
                'test3' => 'test2',

                //Recursive
                'test6' => 'test5',
                'test5' => 'test4',
                'test4' => 'test'
            ],
            'databases' => [
                'test'  => [],
                'test2' => [],
            ]
        ]);

        $this->assertTrue($config->hasDatabase('test'));
        $this->assertTrue($config->hasDatabase('test2'));
        $this->assertFalse($config->hasDatabase('test4'));
        $this->assertFalse($config->hasDatabase('test5'));
        $this->assertFalse($config->hasDatabase('test6'));

        $this->assertSame('test2', $config->resolveAlias('test3'));

        $this->assertSame('test', $config->resolveAlias('test6'));
        $this->assertSame('test', $config->resolveAlias('test5'));
        $this->assertSame('test', $config->resolveAlias('test4'));
    }

    public function testDatabaseOptions()
    {
        $config = new MongoConfig([
            'default'   => 'database-1',
            'databases' => [
                'test'  => [
                    'server'  => 'some-server',
                    'options' => ['options']
                ],
                'test2' => []
            ]
        ]);

        $this->assertTrue($config->hasDatabase('test'));
        $this->assertSame([
            'server'  => 'some-server',
            'options' => ['options']
        ], $config->databaseOptions('test'));
    }
}