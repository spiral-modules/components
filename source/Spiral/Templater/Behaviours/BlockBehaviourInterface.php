<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Templater\Behaviours;

use Spiral\Templater\BehaviourInterface;

/**
 * Declares to Node that it must create logical block.
 */
interface BlockBehaviourInterface extends BehaviourInterface
{
    /**
     * Declared block name.
     *
     * @return string
     */
    public function getName();
}