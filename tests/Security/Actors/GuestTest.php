<?php
/**
 * components
 *
 * @author    Dmitry Mironov <dmitry.mironov@spiralscout.com>
 */

namespace Spiral\Security\Actors;

use Spiral\Security\ActorInterface;


/**
 * Class GuestTest
 *
 * @package Spiral\Security\Actors
 */
class GuestTest extends \PHPUnit_Framework_TestCase
{
    public function testGetRoles()
    {
        /** @var ActorInterface $actor */
        $actor = new Guest();

        $this->assertEquals([Guest::ROLE], $actor->getRoles());
    }
}