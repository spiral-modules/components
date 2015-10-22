<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Templater\Behaviours;

use Spiral\Templater\BehaviourInterface;
use Spiral\Templater\Node;

/**
 * Include behaviour mount external node with it's content into parent node tree. Included node
 * might have different supervisor instance.
 */
interface IncludeBehaviourInterface extends BehaviourInterface
{
    /**
     * Create node to be included into parent container (node).
     *
     * @return Node
     */
    public function createNode();
}