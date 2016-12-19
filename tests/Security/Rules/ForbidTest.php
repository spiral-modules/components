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
 * Class ForbidTest
 *
 * @package Spiral\Security\Rules
 */
class ForbidTest extends \PHPUnit_Framework_TestCase
{
    public function testAllow()
    {
        /** @var RuleInterface $rule */
        $rule = new Forbid();
        /** @var ActorInterface $actor */
        $actor = $this->createMock(ActorInterface::class);

        $this->assertFalse($rule->allows($actor, '', []));
    }
}