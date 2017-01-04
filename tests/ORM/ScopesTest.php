<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\Core\Container;
use Spiral\ORM\ORMInterface;
use Spiral\Tests\Core\Fixtures\SharedComponent;
use Spiral\Tests\ORM\Fixtures\User;
use Spiral\Tests\ORM\Traits\ORMTrait;

class ScopesTest// extends \PHPUnit_Framework_TestCase
{
    use ORMTrait;

    /**
     * @var Container
     */
    protected $container;

    protected $odm;

    public function tearDown()
    {
        //Restoring global scope
        SharedComponent::shareContainer(null);
    }

    public function setUp()
    {
        $this->container = new Container();
        $odm = $this->makeODM();
        $builder = $this->makeBuilder();

        $builder->addSchema($this->makeSchema(User::class));
        $odm->buildSchema($builder);

        $this->container->bind(ORMInterface::class, $odm);
    }

    /**
     * @expectedException \Spiral\Core\Exceptions\ScopeException
     * @expectedExceptionMessage  Unable to saturate 'Spiral\ORM\ORMInterface': no container
     *                            available
     */
    public function testNoScope()
    {
        $user = new User();
    }

    public function testWithScope()
    {
        SharedComponent::shareContainer($this->container);
        $user = new User();
        $this->assertInstanceOf(User::class, $user);
    }
}