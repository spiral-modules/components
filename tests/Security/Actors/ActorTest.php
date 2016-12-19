<?php
/**
 * components
 *
 * @author    Dmitry Mironov <dmitry.mironov@spiralscout.com>
 */

namespace Spiral\Security\Actors;

use Spiral\Security\ActorInterface;


/**
 * Class ActorTest
 *
 * @package Spiral\Security\Actors
 */
class ActorTest extends \PHPUnit_Framework_TestCase
{
    public function testGetRoles()
    {
        $roles = ['user', 'admin'];

        /** @var ActorInterface $actor */
        $actor = new Actor($roles);

        $this->assertEquals($roles, $actor->getRoles());
    }
}