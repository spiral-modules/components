<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Nodes;

use Spiral\ORM\Entities\Nodes\Traits\DuplicateTrait;
use Spiral\ORM\Exceptions\LoaderException;

/**
 * Node with ability to push it's data into referenced tree location.
 */
class SingularNode extends AbstractNode
{
    use DuplicateTrait;

    /**
     * @var string
     */
    protected $localKey;

    /**
     * @param array       $columns
     * @param string      $localKey  Inner relation key (for example user_id)
     * @param string|null $parentKey Outer (parent) relation key (for example id = parent.id)
     * @param array       $primaryKeys
     */
    public function __construct(
        array $columns = [],
        string $localKey,
        string $parentKey,
        array $primaryKeys = []
    ) {
        parent::__construct($columns, $parentKey);
        $this->localKey = $localKey;

        //Using primary keys (if any) to de-duplicate results
        $this->duplicateCriteria = $primaryKeys;
    }

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
        $this->parent->mount(
            $this->container,
            $this->outerKey,
            $data[$this->localKey],
            $data
        );
    }
}