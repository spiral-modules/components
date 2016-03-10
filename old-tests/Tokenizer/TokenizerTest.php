<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\tests\Cases\Tokenizer;

use Spiral\Core\HippocampusInterface;
use Spiral\Files\FileManager;
use Spiral\Tokenizer\Configs\TokenizerConfig;
use Spiral\Tokenizer\Tokenizer;
use Spiral\Tokenizer\TokenizerInterface;

class TokenizerTest extends \PHPUnit_Framework_TestCase
{
    public function testTokens()
    {
        $tokenizer = new Tokenizer(
            new FileManager(),
            new TokenizerConfig(),
            $this->getMock(HippocampusInterface::class)
        );

        $expectedTokens = token_get_all(file_get_contents(__FILE__));

        foreach ($tokenizer->fetchTokens(__FILE__) as $id => $token) {
            $this->assertTrue(array_key_exists(TokenizerInterface::TYPE, $token));
            $this->assertTrue(array_key_exists(TokenizerInterface::LINE, $token));
            $this->assertTrue(array_key_exists(TokenizerInterface::CODE, $token));

            $expected = $expectedTokens[$id];

            if (is_array($expected)) {
                $this->assertEquals($expected, $token);
            } else {
                $this->assertEquals($expected, $token[TokenizerInterface::CODE]);
            }
        }
    }
}
