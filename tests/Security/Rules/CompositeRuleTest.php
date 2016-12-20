<?php
/**
 * components
 *
 * @author    Dmitry Mironov <dmitry.mironov@spiralscout.com>
 */

namespace Spiral\Tests\Security\Rules;

use Spiral\Security\RulesInterface;
use Spiral\Tests\Security\Rules\Fixtures\AllCompositeRule;
use Spiral\Tests\Security\Rules\Fixtures\OneCompositeRule;


/**
 * Class CompositeRuleTest
 *
 * @package Spiral\Tests\Security\Rules
 */
class CompositeRuleTest extends \PHPUnit_Framework_TestCase
{
    private $repository;

    protected function setUp()
    {
        $this->repository = $this->createMock(RulesInterface::class);
    }

    public function testAllow()
    {
        $rule = new AllCompositeRule($this->repository);
        $rule = new OneCompositeRule($this->repository);
    }
}