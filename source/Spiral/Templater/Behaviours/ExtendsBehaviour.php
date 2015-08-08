<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Templater\Behaviours;

use Spiral\Templater\Exceptions\TemplaterException;
use Spiral\Templater\ImportInterface;
use Spiral\Templater\Node;
use Spiral\Templater\Templater;

/**
 * {@inheritdoc}
 */
class ExtendsBehaviour implements ExtendsBehaviourInterface
{
    /**
     * Parent (extended) node, treat it as page or element layout.
     *
     * @var Node
     */
    private $parent = null;

    /**
     * Attributes defined using extends tag.
     *
     * @var array
     */
    private $attributes = [];

    /**
     * @param Node  $parent
     * @param array $attributes
     */
    public function __construct(Node $parent, array $attributes)
    {
        $this->parent = $parent;
        $this->attributes = $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Every import defined on parent level.
     *
     * @return ImportInterface[]
     */
    public function getImports()
    {
        $supervisor = $this->parent->getSupervisor();

        if (!$supervisor instanceof Templater) {
            throw new TemplaterException("ExtendsBehaviour must be executed using Templater.");
        }

        return $supervisor->getImports();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlocks()
    {
        return $this->attributes;
    }
}