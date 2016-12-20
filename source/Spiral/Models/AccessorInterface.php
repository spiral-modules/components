<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models;

use Spiral\Models\Exceptions\AccessorExceptionInterface;
use Spiral\Validation\ValueInterface;

/**
 * Accessors used to mock access to model field, control value setting, serializing and etc.
 */
interface AccessorInterface extends ValueInterface, \JsonSerializable
{
    /**
     * Change mocked data.
     *
     * @see packValue
     *
     * @param mixed $data
     *
     * @throws AccessorExceptionInterface
     */
    public function setValue($data);
}
