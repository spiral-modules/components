<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Translator\Traits;

use Spiral\Core\Component;
use Spiral\Translator\TranslatorInterface;

/**
 * Provides static instance of TranslatorInterface to Translator trait.
 */
class TraitSupport extends Component
{
    /**
     * Check if translator is available.
     */
    public static function hasTranslator()
    {
        if (
            empty(self::staticContainer())
            || !self::staticContainer()->has(TranslatorInterface::class)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get statically available translator component.
     *
     * @return TranslatorInterface
     */
    public static function getTranslator()
    {
        return self::staticContainer()->get(TranslatorInterface::class);
    }
}