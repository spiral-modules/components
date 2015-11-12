<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Core\Traits;

use Spiral\Core\ContainerInterface;
use Spiral\Core\Exceptions\MissingContainerException;

/**
 * Saturate optional constructor or method argument (class) using internal (usually static)
 * container. In most of cases trait is doing nothing since spiral Container populates even
 * optional class dependencies.
 *
 * Avoid using this trait in custom code, it's only a development sugar.
 */
trait SaturateTrait
{
    /**
     * Class specific container.
     *
     * @return ContainerInterface
     */
    abstract protected function container();

    /**
     * Must be used only to resolve optional constructor arguments. Use in classes which are
     * generally resolved using Container. Default value MUST always be supplied from outside.
     *
     * @internal Do not use for business logic.
     * @param mixed  $default Default value.
     * @param string $class   Requested class.
     * @return mixed|null|object
     */
    private function saturate($default, $class)
    {
        if (!empty($default)) {
            return $default;
        }

        if (empty($this->container())) {
            throw new MissingContainerException(
                "Unable to saturate '{$class}', global container were not set."
            );
        }

        //Only when global container is set
        return $this->container()->get($class);
    }
}
