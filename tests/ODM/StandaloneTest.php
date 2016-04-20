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

    protected function createLocator($directory)
    {
        $memory = m::mock(HippocampusInterface::class);
        $memory->shouldReceive('loadData')->andReturn(null);
        $memory->shouldReceive('saveData')->andReturn(null);

        $tokenizer = new Tokenizer(new FileManager(), new TokenizerConfig(), $memory);

        $finder = new Finder();
        $finder->in($directory);

        return new ClassLocator($tokenizer, $finder);
    }

    protected function createODM()
    {
        $container = new Container();

        $memory = m::mock(HippocampusInterface::class);
        $memory->shouldReceive('loadData')->andReturn(null);

        $odm = new ODM($this->createConfig(), $memory, $container);

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
}