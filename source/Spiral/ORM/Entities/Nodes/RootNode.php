<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Nodes;

use Spiral\ORM\Entities\Nodes\Traits\DuplicateTrait;

class RootNode extends AbstractNode
{
    use DuplicateTrait;

    /**
     * Array used to aggregate all nested node results in a form of tree.
     *
     * @var array
     */
    private $result = [];

    /**
     * @param array  $columns
     * @param string $primaryKey
     */
    public function __construct(array $columns = [], string $primaryKey)
    {
        parent::__construct($columns, null);
        $this->primaryKey = $primaryKey;
    }

    /**
     * Get resulted tree.
     *
     * @return array
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * Destructing.
     */
    public function __destruct()
    {
        $this->result = [];
        parent::__destruct();
    }

    /**
     * {@inheritdoc}
     */
    protected function pushData(array &$data)
    {
        $this->result[] = &$data;
    }
}