<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\tests\Cases\Database;

use Spiral\Database\Configs\DatabasesConfig;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultDatabase()
    {
        $config = new DatabasesConfig([
            'default' => 'database-1'
        ]);

        $this->assertSame('database-1', $config->defaultDatabase());
    }

    public function testHasDatabase()
    {
        $config = new DatabasesConfig([
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

    public function testDatabaseDriver()
    {
        $config = new DatabasesConfig([
            'default'   => 'database-1',
            'databases' => [
                'test'  => [
                    'connection' => 'abc'
                ],
                'test2' => [
                    'connection' => 'bce'
                ],
            ]
        ]);

        $this->assertSame('abc', $config->databaseDriver('test'));
        $this->assertSame('bce', $config->databaseDriver('test2'));
    }

    public function testDatabasePrefix()
    {
        $config = new DatabasesConfig([
            'default'   => 'database-1',
            'databases' => [
                'test'  => [
                    'prefix' => 'abc'
                ],
                'test2' => [
                    'prefix' => 'bce'
                ],
            ]
        ]);

        $this->assertSame('abc', $config->databasePrefix('test'));
        $this->assertSame('bce', $config->databasePrefix('test2'));
    }

    public function testDatabaseNames()
    {
        $config = new DatabasesConfig([
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
        $config = new DatabasesConfig([
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

    public function testHasDriver()
    {
        $config = new DatabasesConfig([
            'connections' => [
                'test'  => [],
                'test2' => [],
            ]
        ]);

        $this->assertTrue($config->hasDriver('test'));
        $this->assertTrue($config->hasDriver('test2'));
        $this->assertFalse($config->hasDriver('database-1'));
    }

    public function testDriverClass()
    {
        $config = new DatabasesConfig([
            'default'   => 'database-1',
            'connections' => [
                'test'  => [
                    'class' => 'abc'
                ],
                'test2' => [
                    'class' => 'bce'
                ],
            ]
        ]);

        $this->assertSame('abc', $config->driverClass('test'));
        $this->assertSame('bce', $config->driverClass('test2'));
    }

    public function testDriverNames()
    {
        $config = new DatabasesConfig([
            'connections' => [
                'test'  => [],
                'test2' => [],
            ]
        ]);

        $this->assertSame(['test', 'test2'], $config->driverNames());
    }


    public function testDriverOptions()
    {
        $config = new DatabasesConfig([
            'connections' => [
                'test'  => [
                    'server'  => 'some-server',
                    'options' => ['options']
                ],
                'test2' => []
            ]
        ]);

        $this->assertTrue($config->hasDriver('test'));
        $this->assertSame([
            'server'  => 'some-server',
            'options' => ['options']
        ], $config->driverOptions('test'));
    }
}