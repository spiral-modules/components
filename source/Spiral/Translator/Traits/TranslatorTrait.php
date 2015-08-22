<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Translator\Traits;

use Spiral\Core\ContainerInterface;
use Spiral\Translator\TranslatorInterface;

/**
 * Add bundle specific translation functionality, class name will be used as translation bundle.
 * In addition every default string message declared in class using [[]] braces can be indexed by
 * spiral application. Use translate() method statically to make it indexable by spiral.
 */
trait TranslatorTrait
{
    /**
     * Has to be declared statically!
     *
     * @return ContainerInterface|null
     */
    abstract public function container();

    /**
     * Translate message using parent class as bundle name. Method will remove string braces ([[ and
     * ]]) if specified.
     *
     * Example: User::translate("User account is invalid.");
     *
     * @param string $string
     * @return string
     */
    public static function translate($string)
    {
        if (
            substr($string, 0, 2) === TranslatorInterface::I18N_PREFIX
            && substr($string, -2) === TranslatorInterface::I18N_POSTFIX
        ) {
            //This string was defined in class attributes
            $string = substr($string, 2, -2);
        }

        $container = self::container();
        if (empty($container) || !$container->has(TranslatorInterface::class)) {
            //Unable to localize
            return $string;
        }

        return $container->get(TranslatorInterface::class)->translate(static::class, $string);
    }
}