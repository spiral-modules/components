<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Support;

/**
 * Provides ability to process permissions as star based patterns.
 *
 * Example:
 * post.*
 * post.(save|delete)
 */
class Patternizer
{
    /**
     * @param string $string
     * @return bool
     */
    public function isPattern($string)
    {
        return strpos($string, '*') !== false || strpos($string, '|') !== false;
    }

    /**
     * @param string $string
     * @param string $pattern
     * @return bool
     */
    public function matches($string, $pattern)
    {
        if (!$this->isPattern($pattern)) {
            return $string === $pattern;
        }

        return (bool)preg_match($this->getRegex($pattern), $string);
    }

    /**
     * @param string $pattern
     * @return string
     */
    private function getRegex($pattern)
    {
        $regex = str_replace('*', '[a-z0-9_\-]+', addcslashes($pattern, '.-'));

        return "#^{$regex}$#i";
    }
}