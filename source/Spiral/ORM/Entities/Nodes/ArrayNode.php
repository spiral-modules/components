<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Nodes;

use Spiral\ORM\Exceptions\LoaderException;

class ArrayNode extends SingularNode
{
    /**
     * {@inheritdoc}
     */
    protected function pushData(array &$data)
    {
        if (empty($this->parent)) {
            throw new LoaderException("Unable to register data tree, parent is missing");
        }

        if (is_null($data[$this->localKey])) {
            //No data was loaded
            return;
        }

        //Mounting parsed data into parent under defined container
        $this->parent->mountArray(
            $this->container,
            $this->outerKey,
            $data[$this->localKey],
            $data
        );
    }
}