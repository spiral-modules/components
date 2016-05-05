<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ODM;

use Spiral\Models\EntityInterface;
use Spiral\Models\PublishableInterface;

/**
 * Declares that object can be embedded into Document as some instance and control it's owm (located
 * in instance) updates, public fields and validations. Basically it's embeddable entity.
 *
 * Compositable instance is primary entity type for ODM.
 *
 * Compositable object can be validated if ValidatesInterface are implemented.
 */
interface CompositableInterface extends DocumentAccessorInterface, PublishableInterface
{
    /**
     * @param mixed|array          $value
     * @param EntityInterface|null $parent
     * @param ODMInterface|null    $odm
     */
    public function __construct($value, EntityInterface $parent = null, ODMInterface $odm = null);
}
