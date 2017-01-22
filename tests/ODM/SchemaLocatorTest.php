<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use Spiral\Core\Container;
use Spiral\ODM\Configs\MutatorsConfig;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\Schemas\DocumentSchema;
use Spiral\ODM\Schemas\SchemaLocator;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Fixtures\UserSource;
use Spiral\Tests\ODM\Traits\ODMTrait;
use Spiral\Tokenizer\ClassesInterface;

class SchemaLocatorTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testLocateDocuments()
    {
        $classes = m::mock(ClassesInterface::class);
        $config = new MutatorsConfig([]);

        $container = new Container();
        $container->bind(ClassesInterface::class, $classes);
        $container->bind(MutatorsConfig::class, $config);

        $locator = new SchemaLocator($container);

        $classes->shouldReceive('getClasses', [DocumentEntity::class])->andReturn([
            User::class  => ['name' => User::class, 'filename' => '~', 'abstract' => false],
            Admin::class => ['name' => Admin::class, 'filename' => '~', 'abstract' => true]
        ]);

        $result = $locator->locateSchemas();
        $this->assertCount(1, $result);

        /**
         * @var DocumentSchema $schema
         */
        $schema = $result[0];
        $this->assertInstanceOf(DocumentSchema::class, $schema);
        $this->assertSame(User::class, $schema->getClass());
    }

    public function testLocateSources()
    {
        $classes = m::mock(ClassesInterface::class);
        $config = new MutatorsConfig([]);

        $container = new Container();
        $container->bind(ClassesInterface::class, $classes);
        $container->bind(MutatorsConfig::class, $config);

        $locator = new SchemaLocator($container);

        $classes->shouldReceive('getClasses', [DocumentSource::class])->andReturn([
            UserSource::class => [
                'name'     => UserSource::class,
                'filename' => '~',
                'abstract' => false
            ],
            Admin::class      => ['name' => Admin::class, 'filename' => '~', 'abstract' => true]
        ]);

        $result = $locator->locateSources();

        $this->assertSame([
            User::class => UserSource::class
        ], $result);
    }
}