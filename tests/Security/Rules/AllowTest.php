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
 * Class AllowTest
 *
 * @package Spiral\Security\Rules
 */
class AllowTest extends \PHPUnit_Framework_TestCase
{
    public function testAllow()
    {
        /** @var RuleInterface $rule */
        $rule = new Allow();
        /** @var ActorInterface $actor */
        $actor = $this->createMock(ActorInterface::class);

        $this->assertTrue($rule->allows($actor, '', []));
    }
}