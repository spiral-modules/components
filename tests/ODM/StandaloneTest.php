<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use Spiral\Core\Container;
use Spiral\Core\HippocampusInterface;
use Spiral\Files\FileManager;
use Spiral\ODM\Configs\ODMConfig;
use Spiral\ODM\ODM;
use Spiral\ODM\ODMInterface;
use Spiral\Tests\ODM\Fixtures\Data;
use Spiral\Tests\ODM\Fixtures\Element;
use Spiral\Tokenizer\ClassLocator;
use Spiral\Tokenizer\Configs\TokenizerConfig;
use Spiral\Tokenizer\Tokenizer;
use Symfony\Component\Finder\Finder;

class StandaloneTest extends \PHPUnit_Framework_TestCase
{
    public function testIndexation()
    {
        $odm = $this->createODM();
        $builder = $odm->schemaBuilder($this->createLocator(__DIR__ . '/Fixtures'));

        $this->assertTrue($builder->hasDocument(Data::class));
        $this->assertTrue($builder->hasDocument(Element::class));
    }

    public function testUpdateSchema()
    {
        $odm = $this->createODM();
        $builder = $odm->schemaBuilder($this->createLocator(__DIR__ . '/Fixtures'));
        $odm->updateSchema($builder);
    }

    public function testEntities()
    {
        $odm = $this->createODM();
        $odm->updateSchema($odm->schemaBuilder($this->createLocator(__DIR__ . '/Fixtures')));

        $data = $odm->document(Data::class);
        $element = $data->elements->create();
        $this->assertInstanceOf(Element::class, $element);
    }

    public function testSerialization()
    {
        $odm = $this->createODM();
        $odm->updateSchema($odm->schemaBuilder($this->createLocator(__DIR__ . '/Fixtures')));

        $model = $odm->document(Data::class, [
                'name'     => 'value',
                'elements' => [
                    ['name' => 'Element A'],
                    ['name' => 'Element B'],
                    ['name' => 'Element C']
                ]
            ]
        );

        $this->assertSame('value', $model->name);
        $model->name = 123;
        $this->assertSame('123', $model->name);

        $this->assertCount(3, $model->elements);

        $this->assertSame([
            'name'     => '123',
            'elements' => [
                ['name' => 'Element A'],
                ['name' => 'Element B'],
                ['name' => 'Element C']
            ]
        ], $model->serializeData());
    }

    protected function createLocator($directory)
    {
        $tokenizer = new Tokenizer(new FileManager(), new TokenizerConfig(), $this->createMemory());

        $finder = new Finder();
        $finder->in($directory);

        return new ClassLocator($tokenizer, $finder);
    }

    protected function createODM()
    {
        $container = new Container();

        $odm = new ODM($this->createConfig(), $this->createMemory(), $container);

        $container->bind(ODM::class, $odm);
        $container->bind(ODMInterface::class, $odm);

        return $odm;
    }

    protected function createConfig()
    {
        return new ODMConfig([
            /*
            * Here you can specify name/alias for database to be treated as default in your application.
            * Such database will be returned from ODM->database(null) call and also can be
            * available using $this->db shared binding.
            */
            'default'   => 'default',
            'aliases'   => [
                'database' => 'default',
                'db'       => 'default',
                'mongo'    => 'default'
            ],
            'databases' => [
                'default' => [
                    'server'   => 'mongodb://localhost:27017',
                    'database' => 'spiral-empty',
                    'options'  => [
                        'connect' => true
                    ]
                ],
                /*{{databases}}*/
            ],
            'schemas'   => [
                /*
                 * Set of mutators to be applied for specific field types.
                 */
                'mutators'       => [
                    'int'     => ['setter' => 'intval'],
                    'float'   => ['setter' => 'floatval'],
                    'string'  => ['setter' => 'strval'],
                    'long'    => ['setter' => 'intval'],
                    'bool'    => ['setter' => 'boolval'],
                    'MongoId' => ['setter' => [ODM::class, 'mongoID']],
                    /*{{mutators}}*/
                ],
                'mutatorAliases' => [
                    /*
                     * Mutator aliases can be used to declare custom getter and setter filter methods.
                     */
                    /*{{mutator-aliases}}*/
                ]
            ]
        ]);
    }

    protected function createMemory()
    {
        $memory = m::mock(HippocampusInterface::class);
        $memory->shouldReceive('loadData')->andReturn(null);
        $memory->shouldReceive('saveData')->andReturn(null);

        return $memory;
    }
}