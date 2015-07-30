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

trait TranslatorTrait
{
    /**
     * Global container access is required in some cases. Method should be declared statically.
     *
     * @return ContainerInterface
     */
    abstract public function container();

    /**
     * Perform automatic message localization. Messages with [[ ]] and without braces accepted.
     * Please use this method statically as in this case it will be correctly indexed. Method will
     * work only with global container is set.
     *
     * Example:
     * User::translate("User account is invalid.");
     *
     * @param string $string
     * @return string
     */
    public static function translate($string)
    {
        $container = self::getContainer();
        if (empty($container) || !$container->hasBinding(TranslatorInterface::class))
        {
            //Unable to localize
            return $string;
        }

        if (
            substr($string, 0, 2) === TranslatorInterface::I18N_PREFIX
            && substr($string, -2) === TranslatorInterface::I18N_POSTFIX
        )
        {
            //This string was defined in class attributes
            $string = substr($string, 2, -2);
        }

        return $container->get(TranslatorInterface::class)->translate(static::class, $string);
    }
}