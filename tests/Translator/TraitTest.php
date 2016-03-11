<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Translator;

use Spiral\Core\Component;
use Spiral\Tests\Core\Fixtures\SampleComponent;
use Spiral\Translator\Traits\TranslatorTrait;

class TraitTest //extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        SampleComponent::shareContainer(null);
    }

    /**
     * @expectedException \Spiral\Core\Exceptions\SugarException
     * @expectedExceptionMessage Unable to get instance of 'TranslatorInterface'
     */
    public function testNoContainer()
    {
        $class = new SayClass();
        $class->saySomething();
    }
}

class SayClass extends Component
{
    use TranslatorTrait;

    const MESSAGE = '[[Hello, {name}!]]';

    /**
     * @return string
     */
    public function saySomething()
    {
        return $this->say('Something');
    }

    /**
     * @param string $name
     * @return string
     */
    public function sayMessage($name)
    {
        return $this->say(self::MESSAGE, compact('name'));
    }
}