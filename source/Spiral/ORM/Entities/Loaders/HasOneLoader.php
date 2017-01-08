<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\Database\Builders\SelectQuery;
use Spiral\Database\Injections\Parameter;
use Spiral\ORM\Entities\Nodes\AbstractNode;
use Spiral\ORM\Entities\Nodes\SinguralNode;
use Spiral\ORM\Record;

/**
 * Dedicated to load HAS_ONE relations, by default loader will prefer to join data into query.
 * Loader support MORPH_KEY.
 *
 * Please note that OUTER and INNER keys defined from perspective of parent (reversed for our
 * purposes).
 */
class HasOneLoader extends RelationLoader
{
    /**
     * Default set of relation options. Child implementation might defined their of default options.
     *
     * @var array
     */
    protected $options = [
        'method' => self::INLOAD,
        'minify' => true,
        'alias'  => null,
        'using'  => null,
        'where'  => null,
    ];

    /**
     * {@inheritdoc}
     */
    protected function configureQuery(SelectQuery $query, array $references = []): SelectQuery
    {
        if ($this->isJoined()) {
            $query->join(
                $this->getMethod() == self::JOIN ? 'INNER' : 'LEFT',
                "{$this->getTable()} AS {$this->getAlias()}",
                [$this->localKey(Record::OUTER_KEY) => $this->parentKey(Record::INNER_KEY)]
            );
        } else {
            //This relation is loaded using external query
            $query->where(
                $this->localKey(Record::OUTER_KEY),
                'IN',
                new Parameter($references)
            );
        }

        return parent::configureQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    protected function initNode(): AbstractNode
    {
        $node = new SinguralNode(
            $this->schema[Record::RELATION_COLUMNS],
            $this->schema[Record::OUTER_KEY],
            $this->schema[Record::INNER_KEY],
            $this->schema[Record::SH_PRIMARIES]
        );

        return $node->asJoined($this->isJoined());
    }
}