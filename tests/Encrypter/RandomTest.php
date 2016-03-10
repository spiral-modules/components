<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Encrypter;

use Spiral\Encrypter\Encrypter;

class RandomTest extends \PHPUnit_Framework_TestCase
{
    public function testRandom()
    {
        $encrypter = $this->makeEncrypter();

        $previousRandoms = [];
        for ($try = 0; $try < 100; $try++) {
            $random = $encrypter->random(32);
            $this->assertTrue(strlen($random) == 32);
            $this->assertNotContains($random, $previousRandoms);
            $previousRandoms[] = $random;
        }
    }

    /**
     * @param string $key
     * @return Encrypter
     */
    protected function makeEncrypter($key = '1234567890123456')
    {
        return new Encrypter($key);
    }
}