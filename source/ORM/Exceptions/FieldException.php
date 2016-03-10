<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Exceptions;

use Spiral\Models\Exceptions\FieldExceptionInterface;

/**
 * When field can not be set or get.
 */
class FieldException extends RecordException implements FieldExceptionInterface
{
}
