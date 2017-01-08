<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Nodes;

/**
 * Node used by loaders which do not load any data.
 */
class NullNode extends AbstractNode
{
    /**
     * {@inheritdoc}
     */
    public function parseRow(string $container, int $dataOffset, array $row)
    {
        //Doing nothing
    }

    /**
     * {@inheritdoc}
     */
    protected function deduplicate(array &$data): bool
    {
        //Doing nothing
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function registerData(string $container, array &$data)
    {
        //Doing nothing
    }
}