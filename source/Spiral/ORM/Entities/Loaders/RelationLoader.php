<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\Database\Builders\SelectQuery;
use Spiral\ORM\Entities\Loaders\Traits\ColumnsTrait;
use Spiral\ORM\Entities\Nodes\AbstractNode;
use Spiral\ORM\Exceptions\LoaderException;
use Spiral\ORM\LoaderInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

/**
 * Provides ability to load relation data in a form of JOIN or external query.
 */
abstract class RelationLoader extends QueryLoader
{
    use ColumnsTrait;

    /**
     * Used to create unique set of aliases for loaded relations.
     *
     * @var int
     */
    private static $countLevels = 0;

    /**
     * Name of relation loader associated with.
     *
     * @var string
     */
    protected $relation;

    /**
     * Default set of relation options. Child implementation might defined their of default options.
     *
     * @var array
     */
    protected $options = [
        'method' => null,
        'join'   => 'INNER',
        'alias'  => null,
        'using'  => null,
        'where'  => null,
    ];

    /**
     * @param string       $class
     * @param string       $relation
     * @param array        $schema
     * @param ORMInterface $orm
     */
    public function __construct(string $class, string $relation, array $schema, ORMInterface $orm)
    {
        parent::__construct($class, $schema, $orm);

        //We need related model primary keys in order to ensure that
        $this->schema[Record::SH_PRIMARIES] = $orm->define($class, ORMInterface::R_PRIMARIES);
        $this->relation = $relation;
    }

    /**
     * {@inheritdoc}
     */
    public function withContext(LoaderInterface $parent, array $options = []): LoaderInterface
    {
        /**
         * @var QueryLoader $parent
         * @var self        $loader
         */
        $loader = parent::withContext($parent, $options);

        if ($loader->getDatabase() != $parent->getDatabase()) {
            if ($loader->isJoined()) {
                throw new LoaderException('Unable to join tables located in different databases');
            }

            //Loader is not joined, let's make sure that POSTLOAD is used
            if ($this->isLoaded()) {
                $loader->options['method'] = self::POSTLOAD;
            }
        }

        //Calculate table alias
        $loader->ensureAlias($parent);

        return $loader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadData(AbstractNode $node)
    {
        if ($this->isJoined()) {
            //We are expecting data to be already loaded via query itself
            return;
        }

        //Query???


        //Loading data

        //Post-loading!!!!!!
        foreach ($this->loaders as $relation => $loader) {
            $loader->loadData($node->fetchNode($relation));
        }
    }

    /**
     * @param SelectQuery $query
     *
     * @return SelectQuery
     */
    protected function configureQuery(SelectQuery $query): SelectQuery
    {
        if ($this->isJoined()) {
            if ($this->isLoaded()) {
                //Mounting columns
                $this->mountColumns($query, true);
            }

            //Mounting joins
            $this->mountJoins($query);
        }

        return parent::configureQuery($query);
    }

    /**
     * Relation table alias.
     *
     * @return string
     */
    protected function getAlias(): string
    {
        if (!empty($this->options['using'])) {
            //We are using another relation (presumably defined by with() to load data).
            return $this->options['using'];
        }

        if (!empty($this->options['alias'])) {
            return $this->options['alias'];
        }

        throw new LoaderException("Unable to resolve loader alias");
    }

    /**
     * Relation columns.
     *
     * @return array
     */
    protected function getColumns(): array
    {
        return $this->schema[Record::RELATION_COLUMNS];
    }

    /**
     * Indicated that loaded must generate JOIN statement.
     *
     * @return bool
     */
    protected function isJoined(): bool
    {
        if (!empty($this->options['using'])) {
            return true;
        }

        return in_array($this->getMethod(), [self::INLOAD, self::JOIN, self::LEFT_JOIN]);
    }

    /**
     * Indication that loader want to load data.
     *
     * @return bool
     */
    protected function isLoaded(): bool
    {
        return $this->getMethod() !== self::JOIN && $this->getMethod() !== self::LEFT_JOIN;
    }

    /**
     * Get load method.
     *
     * @return int
     */
    protected function getMethod(): int
    {
        return $this->options['method'];
    }

    /**
     * Ensure table alias.
     *
     * @param QueryLoader $parent
     */
    protected function ensureAlias(QueryLoader $parent)
    {
        //Let's calculate loader alias
        if (empty($this->options['alias'])) {
            if ($this->isLoaded() && $this->isJoined()) {
                //Let's create unique alias, we are able to do that for relations just loaded
                $this->options['alias'] = 'd' . decoct(++self::$countLevels);
            } else {
                //Let's use parent alias to continue chain
                $this->options['alias'] = $parent->getAlias() . '_' . $this->relation;

            }
        }
    }

    /**
     * Set required set of join for a given query.
     *
     * @param SelectQuery $query
     *
     * @return SelectQuery
     */
    abstract protected function mountJoins(SelectQuery $query);
}