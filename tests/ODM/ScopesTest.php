<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Spiral\Core\Container;
use Spiral\ODM\ODMInterface;
use Spiral\Tests\Core\Fixtures\SampleComponent;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class ScopesTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    /**
     * @var Container
     */
    protected $container;

    protected $odm;

    public function tearDown()
    {
        //Restoring global scope
        SampleComponent::shareContainer(null);
    }

    public function setUp()
    {
        $this->container = new Container();
        $odm = $this->makeODM();
        $builder = $this->makeBuilder();

        $builder->addSchema($this->makeSchema(User::class));
        $odm->setSchema($builder);

        $this->container->bind(ODMInterface::class, $odm);
    }

    /**
     * @expectedException \Spiral\Core\Exceptions\ScopeException
     * @expectedExceptionMessage  Unable to saturate 'Spiral\ODM\ODMInterface': no container
     *                            available
     */
    public function testNoScope()
    {
        $user = new User();
    }

    public function testWithScope()
    {
        SampleComponent::shareContainer($this->container);
        $user = new User();
        $this->assertInstanceOf(User::class, $user);
    }
}