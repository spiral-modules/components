<?php
/**
 * components
 *
 * @author    Dmitry Mironov <dmitry.mironov@spiralscout.com>
 */

namespace Spiral\Tests\Security\Traits;

use Interop\Container\ContainerInterface;
use Spiral\Core\Exceptions\ScopeException;
use Spiral\Security\GuardInterface;
use Spiral\Security\Traits\GuardedTrait;
use Spiral\Tests\Security\Traits\Fixtures\Guarded;
use Spiral\Tests\Security\Traits\Fixtures\GuardedWithNamespace;


/**
 * Class GuardedTraitTest
 *
 * @package Spiral\Tests\Security\Traits
 */
class GuardedTraitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|GuardedTrait
     */
    private $trait;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|GuardInterface
     */
    private $guard;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ContainerInterface
     */
    private $container;

    public function setUp()
    {
        $this->trait = $this->getMockForTrait(GuardedTrait::class);
        $this->guard = $this->createMock(GuardInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
    }

    public function testGetGuard()
    {
        $this->trait->setGuard($this->guard);
        $this->assertEquals($this->guard, $this->trait->getGuard());
    }

    public function testGetGuardFromContainer()
    {
        $this->container->method('get')->will($this->returnValue($this->guard));
        $this->trait->method('iocContainer')->will($this->returnValue($this->container));
        $this->assertEquals($this->guard, $this->trait->getGuard());
    }

    public function testGetGuardScopeException()
    {
        $this->expectException(ScopeException::class);
        $this->trait->getGuard();
    }

    public function testAllows()
    {
        $permission = 'permission';
        $context = [];

        $this->guard->method('allows')
            ->with($permission, $context)
            ->will($this->returnValue(true));
        $guarded = new Guarded();
        $guarded->setGuard($this->guard);

        $this->assertTrue($guarded->allows($permission, $context));
        $this->assertFalse($guarded->denies($permission, $context));
    }

    public function testResolvePermission()
    {
        $permission = 'permission';

        $guarded = new Guarded();
        $this->assertEquals($permission, $guarded->resolvePermission($permission));

        $guarded = new GuardedWithNamespace();
        $resolvedPermission = GuardedWithNamespace::GUARD_NAMESPACE . '.' . $permission;
        $this->assertEquals($resolvedPermission, $guarded->resolvePermission($permission));
    }
}