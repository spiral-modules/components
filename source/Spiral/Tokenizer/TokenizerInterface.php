<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer;

use Spiral\Tokenizer\Exceptions\ReflectionException;
use Spiral\Tokenizer\Exceptions\TokenizerException;

/**
 * Simple wrapper at top of token_get_all.
 */
interface TokenizerInterface
{
    /**
     * Token array constants.
     */
    const TYPE = 0;
    const CODE = 1;
    const LINE = 2;

    /**
     * Fetch PHP tokens for specified filename. Usually links to token_get_all() function. Every
     * token MUST be converted into array.
     *
     * @param string $filename
     * @return array
     */
    public function fetchTokens($filename);

    /**
     * Get file reflection for given filename.
     *
     * @param string $filename
     * @return ReflectionFileInterface
     * @throws TokenizerException
     * @throws ReflectionException
     */
    public function fileReflection($filename);
}