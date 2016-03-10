<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Core\Exceptions\Container;

use Interop\Container\Exception\ContainerException as ContainerExceptionInterface;
use Spiral\Core\Exceptions\DependencyException;

/**
 * Something inside container.
 */
class ContainerException extends DependencyException implements ContainerExceptionInterface
{
}
