<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Tests\Translator;

use Mockery as m;
use Spiral\Translator\Translator;

class TranslatorTest //extends \PHPUnit_Framework_TestCase
{
    //TODO: add more tests

    public function testIsMessage()
    {
        $this->assertTrue(Translator::isMessage('[[hello]]'));
        $this->assertFalse(Translator::isMessage('hello'));
    }
}