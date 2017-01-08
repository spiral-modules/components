<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Nodes;

use Spiral\ORM\Exceptions\LoaderException;

class ArrayNode extends RelationNode
{
    /**
     * {@inheritdoc}
     */
    protected function registerData(string $container, array &$data)
    {
        if (empty($this->parent)) {
            throw new LoaderException("Unable to register data tree, parent is missing");
        }

        //Mounting parsed data into parent under defined container
        $this->parent->mountArray(
            $container,
            $this->referenceKey,
            $data[$this->localKey],
            $data
        );
    }
}