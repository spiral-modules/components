<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Core;

use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\Traits\SingletonTrait;

/**
 * Spiral Container will treat classes like that as singletons + instance function which works with
 * static container.
 */
abstract class Singleton extends Component implements SingletonInterface
{
    /**
     * instance() function.
     */
    use SingletonTrait;

    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = null;
}