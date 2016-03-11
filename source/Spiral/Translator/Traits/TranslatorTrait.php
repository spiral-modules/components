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
use Spiral\Core\Exceptions\SugarException;
use Spiral\Translator\Translator;
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
     * Example: $this->say("User account is invalid.");
     *
     * @param string $string
     * @param array  $options Interpolation options.
     *
     * @return string
     *
     * @throws SugarException
     */
    protected function say($string, array $options = [])
    {
        if (Translator::isMessage($string)) {
            //This string was defined in class attributes
            $string = substr($string, 2, -2);
        }

        $container = $this->container();
        if (empty($container) || !$container->has(TranslatorInterface::class)) {
            throw new SugarException("Unable to get instance of 'TranslatorInterface'");
        }

        /**
         * @var TranslatorInterface
         */
        $translator = $container->get(TranslatorInterface::class);

        //Translate class string using automatically resolved message domain
        return $translator->trans(
            $string,
            $options,
            $translator->resolveDomain(static::class)
        );
    }

    /**
     * @return ContainerInterface
     */
    abstract protected function container();
}
