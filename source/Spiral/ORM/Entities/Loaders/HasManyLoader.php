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
use Spiral\ORM\Entities\Nodes\ArrayNode;
use Spiral\ORM\Helpers\WhereDecorator;
use Spiral\ORM\Record;

/**
 * Dedicated to load HAS_MANY relation data, POSTLOAD is preferred loading method. Additional where
 * conditions and morph keys are supported.
 *
 * Please note that OUTER and INNER keys defined from perspective of parent (reversed for our
 * purposes).
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

        //Let's use where decorator to set conditions, it will automatically route tokens to valid
        //destination (JOIN or WHERE)
        $decorator = new WhereDecorator(
            $query,
            $this->isJoined() ? 'onWhere' : 'where',
            $this->getAlias()
        );

        if (!empty($this->schema[Record::WHERE])) {
            //Relation WHERE conditions
            $decorator->where($this->schema[Record::WHERE]);
        }

        //User specified WHERE conditions
        if (!empty($this->options['where'])) {
            $decorator->where($this->options['where']);
        }

        return parent::configureQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    protected function initNode(): AbstractNode
    {
        $node = new ArrayNode(
            $this->schema[Record::RELATION_COLUMNS],
            $this->schema[Record::OUTER_KEY],
            $this->schema[Record::INNER_KEY],
            $this->schema[Record::SH_PRIMARIES]
        );

        return $node->asJoined($this->isJoined());
    }
}