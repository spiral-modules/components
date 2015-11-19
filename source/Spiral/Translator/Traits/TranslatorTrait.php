<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Translator\Traits;

use Interop\Container\ContainerInterface;
use Spiral\Core\Container;
use Spiral\Translator\TranslatorInterface;

/**
 * Add bundle specific translation functionality, class name will be used as translation bundle.
 * In addition every default string message declared in class using [[]] braces can be indexed by
 * spiral application.
 */
trait TranslatorTrait
{
    /**
     * Translate message using parent class as bundle name. Method will remove string braces ([[ and
     * ]]) if specified.
     *
     * Example: $this->translate("User account is invalid.");
     *
     * @param string $string
     * @param array  $options Interpolation options.
     * @return string
     */
    protected function translate($string, array $options = [])
    {
        if (
            substr($string, 0, 2) === TranslatorInterface::I18N_PREFIX
            && substr($string, -2) === TranslatorInterface::I18N_POSTFIX
        ) {
            //This string was defined in class attributes
            $string = substr($string, 2, -2);
        }

        if (empty($container = $this->container()) || !$container->has(TranslatorInterface::class)) {
            //No translator available
            return $string;
        }

        return $container->get(TranslatorInterface::class)->translate(
            static::class, $string, $options
        );
    }

    /**
     * @return ContainerInterface
     */
    abstract protected function container();
}