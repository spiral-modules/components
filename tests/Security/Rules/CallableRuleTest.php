<?php
/**
 * components
 *
 * @author    Dmitry Mironov <dmitry.mironov@spiralscout.com>
 */

namespace Spiral\Security\Rules;

use Spiral\Security\ActorInterface;
use Spiral\Security\RuleInterface;


/**
 * Class CallableRuleTest
 *
 * @package Spiral\Security\Rules
 */
class CallableRuleTest extends \PHPUnit_Framework_TestCase
{
    public function testAllow()
    {
        /** @var ActorInterface $actor */
        $actor = $this->createMock(ActorInterface::class);
        $permission = 'foo';
        $context = [];

        /** @var \PHPUnit_Framework_MockObject_MockObject|callable $callable */
        $callable = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();

        $callable->method('__invoke')
            ->with($actor, $permission, $context)
            ->willReturn(true, false);

        /** @var RuleInterface $rule */
        $rule = new CallableRule($callable);

        $this->assertTrue($rule->allows($actor, $permission, $context));
        $this->assertFalse($rule->allows($actor, $permission, $context));
    }
}