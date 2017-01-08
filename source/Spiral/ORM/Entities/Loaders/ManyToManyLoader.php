<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\ORM\Entities\Nodes\AbstractNode;
use Spiral\ORM\Entities\Nodes\NullNode;
use Spiral\ORM\Entities\Nodes\PivotedNode;
use Spiral\ORM\Record;

/**
 * ManyToMany loader will not only load related data, but will include pivot table data into record
 * property "@pivot". Loader support WHERE conditions for both related data and pivot table.
 *
 * It's STRONGLY recommended to load many-to-many data using postload method. However relation still
 * can be used to filter query.
 */
class ManyToManyLoader extends RelationLoader
{
    /**
     * Default set of relation options. Child implementation might defined their of default options.
     *
     * @var array
     */
    protected $options = [
        'method'     => self::POSTLOAD,
        'minify'     => true,
        'alias'      => null,
        'pivotAlias' => null,
        'using'      => null,
        'where'      => null,
    ];

    protected function initNode(): AbstractNode
    {
        return new NullNode();
    }

    /**
     * Pivot table name.
     *
     * @return string
     */
    public function pivotTable(): string
    {
        return $this->schema[Record::PIVOT_TABLE];
    }

    /**
     * Pivot table alias, depends on relation table alias.
     *
     * @return string
     */
    protected function pivotAlias(): string
    {
        if (!empty($this->options['pivotAlias'])) {
            return $this->options['pivotAlias'];
        }

        return $this->getAlias() . '_pivot';
    }
}