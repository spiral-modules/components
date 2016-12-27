<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use MongoDB\Collection;
use Spiral\Core\Container;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\MongoManager;
use Spiral\ODM\ODM;
use Spiral\ODM\ODMInterface;
use Spiral\Tests\Core\Fixtures\SampleComponent;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class SourceTraitTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var MongoManager
     */
    protected $manager;

    public function tearDown()
    {
        //Restoring global scope
        SampleComponent::shareContainer(null);
    }

    public function setUp()
    {
        $this->container = new Container();
        $odm = $this->makeODM($this->manager = m::mock(MongoManager::class));
        $builder = $this->makeBuilder();

        $builder->addSchema($this->makeSchema(User::class));
        $odm->setSchema($builder);

        $this->container->bind(ODMInterface::class, ODM::class);
        $this->container->bind(ODM::class, $odm);
    }

    /**
     * @expectedException \Spiral\Core\Exceptions\ScopeException
     * @expectedExceptionMessage Unable to get 'Spiral\Tests\ODM\Fixtures\User' source, no
     *                           container scope is available
     */
    public function testNoScope()
    {
        User::source();
    }

    public function testSourceMethod()
    {
        SampleComponent::shareContainer($this->container);

        $source = User::source();
        $this->assertInstanceOf(DocumentSource::class, $source);
    }

    public function testSelector()
    {
        SampleComponent::shareContainer($this->container);

        $database = m::mock(MongoDatabase::class);
        $this->manager->shouldReceive('database')->with(null)->andReturn($database);
        $database->shouldReceive('selectCollection')->with('users')->andReturn(m::mock(Collection::class));

        $selector = User::find();
        $this->assertInstanceOf(DocumentSelector::class, $selector);
    }
}