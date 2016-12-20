<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models;

use Spiral\Models\Exceptions\AccessorExceptionInterface;

/**
 * Accessors used to mock access to model field, control value setting, serializing and etc.
 */
interface AccessorInterface extends \JsonSerializable
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

    /**
     * Convert object data into serialized value (array or string for example).
     *
     * @return mixed
     *
     * @throws AccessorExceptionInterface
     */
    public function packValue();
}