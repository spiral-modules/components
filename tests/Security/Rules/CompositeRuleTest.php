<?php
/**
 * components
 *
 * @author    Dmitry Mironov <dmitry.mironov@spiralscout.com>
 */

namespace Spiral\Tests\Security\Rules;

use Spiral\Security\ActorInterface;
use Spiral\Security\RuleInterface;
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
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ActorInterface $callable
     */
    private $actor;

    public function setUp()
    {
        $this->actor = $this->createMock(ActorInterface::class);
    }

    /**
     * @param $expected
     * @param $compositeRuleClass
     * @param $rules
     *
     * @dataProvider allowsProvider
     */
    public function testAllow($expected, $compositeRuleClass, $rules)
    {
        $repository = $this->createRepository($rules);

        /** @var RuleInterface $rule */
        $rule = new $compositeRuleClass($repository);
        $this->assertEquals($expected, $rule->allows($this->actor, 'user.create', []));
    }

    public function allowsProvider()
    {
        return [
            [
                true,
                AllCompositeRule::class,
                [$this->allowRule(), $this->allowRule(), $this->allowRule()]
            ],
            [
                false,
                AllCompositeRule::class,
                [$this->allowRule(), $this->allowRule(), $this->forbidRule()]
            ],
            [
                true,
                OneCompositeRule::class,
                [$this->allowRule(), $this->forbidRule(), $this->forbidRule()]
            ],
            [
                true,
                OneCompositeRule::class,
                [$this->allowRule(), $this->allowRule(), $this->allowRule()]
            ],
            [
                false,
                OneCompositeRule::class,
                [$this->forbidRule(), $this->forbidRule(), $this->forbidRule()]
            ],
        ];
    }

    /**
     * @param array $rules
     * @return RulesInterface
     */
    private function createRepository(array $rules): RulesInterface
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|RulesInterface $repository */
        $repository = $this->createMock(RulesInterface::class);

        $repository->method('get')
            ->will(new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($rules));

        return $repository;
    }

    /**
     * @return RuleInterface
     */
    private function allowRule(): RuleInterface
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|RuleInterface $rule */
        $rule = $this->createMock(RuleInterface::class);
        $rule->method('allows')->willReturn(true);

        return $rule;
    }

    /**
     * @return RuleInterface
     */
    private function forbidRule(): RuleInterface
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|RuleInterface $rule */
        $rule = $this->createMock(RuleInterface::class);
        $rule->method('allows')->willReturn(false);

        return $rule;
    }
}