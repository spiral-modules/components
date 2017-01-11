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
    public function parseRow(int $dataOffset, array $row): int
    {
        //Doing nothing
        return 0;
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
    protected function pushData(array &$data)
    {
        //Nothing to do
    }
}