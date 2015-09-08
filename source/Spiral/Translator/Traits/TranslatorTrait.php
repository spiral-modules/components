<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Translator\Traits;

use Spiral\Core\Container;
use Spiral\Translator\Translator;

/**
 * Add bundle specific translation functionality, class name will be used as translation bundle.
 * In addition every default string message declared in class using [[]] braces can be indexed by
 * spiral application. Use translate() method statically to make it indexable by spiral.
 *
 * Trait uses global container and default Translator implementation (for now, i'm currently
 * thinking how to use static container without violating anything).
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
    public static function translate($string, array $options = [])
    {
        if (
            substr($string, 0, 2) === Translator::I18N_PREFIX
            && substr($string, -2) === Translator::I18N_POSTFIX
        ) {
            //This string was defined in class attributes
            $string = substr($string, 2, -2);
        }

        //This code will work only when global container is set (see Component::staticContainer).
        return Translator::instance()->translate(static::class, $string, $options);
    }
}