<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Translator\Traits;

use Spiral\Core\Container;
use Spiral\Translator\TranslatorInterface;

/**
 * Add bundle specific translation functionality, class name will be used as translation bundle.
 * In addition every default string message declared in class using [[]] braces can be indexed by
 * spiral application. Use translate() method statically to make it indexable by spiral.
 */
trait TranslatorTrait
{
    /**
     * Translate message using parent class as bundle name. Method will remove string braces ([[ and
     * ]]) if specified.
     *
     * Example: User::translate("User account is invalid.");
     *
     * @see Component::staticContainer()
     * @param string $string
     * @param array  $options Interpolation options.
     * @return string
     */
    protected static function translate($string, array $options = [])
    {
        if (
            substr($string, 0, 2) === TranslatorInterface::I18N_PREFIX
            && substr($string, -2) === TranslatorInterface::I18N_POSTFIX
        ) {
            //This string was defined in class attributes
            $string = substr($string, 2, -2);
        }

        if (!TraitSupport::hasTranslator()) {
            //No translator defined
            return $string;
        }

        return TraitSupport::getTranslator()->translate(static::class, $string, $options);
    }
}