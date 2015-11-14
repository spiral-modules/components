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
     * Accessors creation flow is unified and must be performed without Container for performance
     * reasons.
     *
     * @param mixed           $value
     * @param EntityInterface $parent
     * @throws AccessorExceptionInterface
     */
    public function __construct($value, EntityInterface $parent);

    /**
     * Must embed accessor to another parent model. Allowed to clone itself.
     *
     * @param EntityInterface $parent
     * @return static
     * @throws AccessorExceptionInterface
     */
    public function embed(EntityInterface $parent);

    /**
     * Change mocked data.
     *
     * @param mixed $data
     * @throws AccessorExceptionInterface
     */
    public function setValue($data);
}