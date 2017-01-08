<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\Database\Builders\SelectQuery;
use Spiral\ORM\Entities\Nodes\AbstractNode;
use Spiral\ORM\Entities\Nodes\ArrayNode;
use Spiral\ORM\Record;

/**
 * Dedicated to load HAS_MANY relation data, POSTLOAD is preferred loading method. Additional where
 * conditions and morph keys are supported.
 */
class HasManyLoader extends RelationLoader
{
    /**
     * Default set of relation options. Child implementation might defined their of default options.
     *
     * @var array
     */
    protected $options = [
        'method' => self::POSTLOAD,
        'join'   => 'INNER',
        'alias'  => null,
        'using'  => null,
        'where'  => null,
    ];

    /**
     * {@inheritdoc}
     */
    protected function mountJoins(SelectQuery $query)
    {
        $query->join(
            $this->getMethod() == self::JOIN ? 'INNER' : 'LEFT',
            "{$this->getTable()} AS {$this->getAlias()}",
            [
                $this->localKey(Record::OUTER_KEY) => $this->parentKey(Record::INNER_KEY)
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function initNode(): AbstractNode
    {
        return new ArrayNode(
            $this->schema[Record::RELATION_COLUMNS],
            $this->schema[Record::OUTER_KEY],
            $this->schema[Record::INNER_KEY],
            $this->schema[Record::SH_PRIMARIES]
        );
    }

    /**
     * Generate sql identifier using loader alias and value from relation definition. Key name to be
     * fetched from schema.
     *
     * Example:
     * $this->getKey(Record::OUTER_KEY);
     *
     * @param string $key
     *
     * @return string|null
     */
    protected function localKey($key): string
    {
        return $this->getAlias() . '.' . $this->schema[$key];
    }

    /**
     * Get parent identifier based on relation configuration key.
     *
     * @param $key
     *
     * @return string
     */
    protected function parentKey($key): string
    {
        return $this->parent->getAlias() . '.' . $this->schema[$key];
    }
}