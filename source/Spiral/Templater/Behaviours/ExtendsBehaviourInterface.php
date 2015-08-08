<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Templater\Behaviours;

use Spiral\Templater\BehaviourInterface;
use Spiral\Templater\Node;

/**
 * Declares to node that it's blocks should extend parent node. Parent imports will be merged with node
 * content.
 */
interface ExtendsBehaviourInterface extends BehaviourInterface
{
    /**
     * Get parent Node (layout) to be extended.
     *
     * @return Node
     */
    public function getParent();

    /**
     * Get all parent blocks created at moment of extending.
     *
     * @return array
     */
    public function getBlocks();
}