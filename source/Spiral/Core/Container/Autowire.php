<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Core\Container;

use Interop\Container\ContainerInterface;

/**
 * Provides ability to delegate option to container.
 */
final class Autowire
{
    /**
     * Delegation target
     *
     * @var mixed
     */
    private $alias;

    /**
     * Autowire constructor.
     *
     * @param string $alias
     */
    public function __construct(string $alias)
    {
        $this->alias = $alias;
    }

    /**
     * @param \Interop\Container\ContainerInterface $container
     *
     * @return mixed
     *
     * @throws \Interop\Container\Exception\NotFoundException  No entry was found for this
     *                                                         identifier.
     * @throws \Interop\Container\Exception\ContainerException Error while retrieving the entry.
     */
    public function resolve(ContainerInterface $container)
    {
        return $container->get($this->alias);
    }

    /**
     * @param $an_array
     *
     * @return static
     */
    public static function __set_state($an_array)
    {
        return new static($an_array['alias']);
    }
}