<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ODM\Exceptions;

use Spiral\Models\Exceptions\AccessorExceptionInterface;

/**
 * Generic ODM accessor exception.
 */
class AccessorException extends DocumentException implements AccessorExceptionInterface
{
}
