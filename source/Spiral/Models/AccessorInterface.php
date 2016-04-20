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
     * Must embed accessor to another parent model. Allowed to clone itself.
     *
     * @param EntityInterface $parent
     *
     * @return static
     *
     * @throws AccessorExceptionInterface
     */
    public function embed(EntityInterface $parent);

    /**
     * Change mocked data.
     *
     * @see serializeData
     *
     * @param mixed $data
     *
     * @throws AccessorExceptionInterface
     */
    public function setValue($data);
}
