<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ODM;

use Spiral\Models\EntityInterface;

/**
 * Declares that object can be embedded into Document as some instance and control it's owm (located
 * in instance) updates, public fields and validations. Basically it's embeddable entity.
 *
 * Compositable instance is primary entity type for ODM.
 *
 * Compositable object can be validated if ValidatesInterface are implemented.
 */
interface CompositableInterface extends DocumentAccessorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param ODM   $odm     ODM component if any.
     * @param mixed $options Implementation specific options. In ODM will always contain field type.
     */
    public function __construct(
        $value,
        EntityInterface $parent = null,
        ODM $odm = null,
        $options = null
    );

    /**
     * Every composited object must know how to give it's public data (safe to send to client) to
     * parent.
     *
     * @return array
     */
    public function publicFields();
}
