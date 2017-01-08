<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Nodes;

use Spiral\ORM\Exceptions\LoaderException;
use Spiral\ORM\Exceptions\NodeException;

/**
 * Represents data node in a tree with ability to parse line of results, split it into sub
 * relations, aggregate reference keys and etc.
 */
abstract class AbstractNode
{
    /**
     * Column names to be used to hydrate based on given query rows.
     *
     * @var array
     */
    private $columns = [];

    /**
     * @var int
     */
    private $countColumns = 0;

    /**
     * Set of keys to be aggregated by Parser while parsing results.
     *
     * @var array
     */
    private $trackReferences = [];

    /**
     * Tree parts associated with reference keys and key values.
     *
     * $this->collectedReferences[id][ID_VALUE] = [ITEM1, ITEM2, ...].
     *
     * @var array
     */
    private $references = [];

    /**
     * Declared column which must be aggregated in a parent node. i.e. Parent Key
     *
     * @var null|string
     */
    protected $referenceKey = null;

    /**
     * @invisible
     * @var AbstractNode
     */
    protected $parent;

    /**
     * @var AbstractNode[]
     */
    protected $nodes = [];

    /**
     * @param array       $columns
     * @param string|null $referenceKey Defines column name in parent Node to be aggregated.
     */
    public function __construct(array $columns = [], string $referenceKey = null)
    {
        $this->columns = $columns;
        $this->countColumns = count($columns);

        $this->referenceKey = $referenceKey;
    }

    /**
     * Register new node into NodeTree. Nodes used to convert flat results into tree representation
     * using reference aggregations.
     *
     * @param string       $container
     * @param AbstractNode $node
     *
     * @throws NodeException
     */
    final public function registerNode(string $container, AbstractNode $node)
    {
        $node->parent = $this;
        $this->nodes[$container] = $node;

        if (!empty($node->referenceKey)) {
            //This will make parser to aggregate such key in order to be used in later statement
            $this->trackReference($node->referenceKey);
        }
    }

    /**
     * Fetch sub node.
     *
     * @param string $container
     *
     * @return AbstractNode
     *
     * @throws NodeException
     */
    final public function fetchNode(string $container): AbstractNode
    {
        if (!isset($this->nodes[$container])) {
            throw new NodeException("Undefined node {$container}");
        }

        return $this->nodes[$container];
    }

    /**
     * Parser result work, fetch data and mount it into parent tree.
     *
     * @param string $container Container name (which Node belongs to)
     * @param int    $dataOffset
     * @param array  $row
     */
    public function parseRow(string $container, int $dataOffset, array $row)
    {
        //Fetching Node specific data from resulted row
        $data = $this->fetchData($dataOffset, $row);

        if ($this->deduplicate($data)) {
            //Create reference keys
            $this->collectReferences($data);

            //Make sure that all nested relations are registered
            $this->ensurePlaceholders($data);

            //Add data into result set
            $this->registerData($container, $data);
        }

        foreach ($this->nodes as $container => $node) {
            $node->parseRow($container, $this->countColumns + $dataOffset, $row);
        }
    }

    /**
     * In many cases (for example if you have inload of HAS_MANY relation) record data can be
     * replicated by many result rows (duplicated). To prevent wrong data linking we have to
     * deduplicate such records. This is only internal loader functionality and required due data
     * tree are built using php references.
     *
     * Method will return true if data is unique handled before and false in opposite case.
     * Provided data array will be automatically linked with it's unique state using references.
     *
     * @param array $data Reference to parsed record data, reference will be pointed to valid and
     *                    existed data segment if such data was already parsed.
     *
     * @return bool Must return TRUE what data is unique in this selection.
     */
    abstract protected function deduplicate(array &$data): bool;

    /**
     * Register data result.
     *
     * @param string $container
     * @param array  $data
     */
    abstract protected function registerData(string $container, array &$data);

    /**
     * Mount record data into internal data storage under specified container using reference key
     * (inner key) and reference criteria (outer key value).
     *
     * Example (default ORM Loaders):
     * $this->parent->mount('profile', 'id', 1, [
     *      'id' => 100,
     *      'user_id' => 1,
     *      ...
     * ]);
     *
     * In this example "id" argument is inner key of "user" record and it's linked to outer key
     * "user_id" in "profile" record, which defines reference criteria as 1.
     *
     * Attention, data WILL be referenced to new memory location!
     *
     * @param string $container
     * @param string $key
     * @param mixed  $criteria
     * @param array  $data      Data must be referenced to existed set if it was registered
     *                          previously.
     *
     * @throws LoaderException
     */
    final protected function mount(
        string $container,
        string $key,
        $criteria,
        array &$data
    ) {
        foreach ($this->references[$key][$criteria] as &$subset) {
            if (isset($subset[$container])) {
                //Back reference!
                $data = &$subset[$container];
            } else {
                $subset[$container] = &$data;
            }

            unset($subset);
        }
    }

    /**
     * Mount record data into internal data storage under specified container using reference key
     * (inner key) and reference criteria (outer key value).
     *
     * Example (default ORM Loaders):
     * $this->parent->mountArray('comments', 'id', 1, [
     *      'id' => 100,
     *      'user_id' => 1,
     *      ...
     * ]);
     *
     * In this example "id" argument is inner key of "user" record and it's linked to outer key
     * "user_id" in "profile" record, which defines reference criteria as 1.
     *
     * Add added records will be added as array items.
     *
     * @param string $container
     * @param string $key
     * @param mixed  $criteria
     * @param array  $data      Data must be referenced to existed set if it was registered
     *                          previously.
     *
     * @throws LoaderException
     */
    final protected function mountArray(
        string $container,
        string $key,
        $criteria,
        array &$data
    ) {
        foreach ($this->references[$key][$criteria] as &$subset) {
            if (!in_array($data, $subset[$container])) {
                $subset[$container][] = &$data;
            }

            unset($subset);
            continue;
        }
    }

    /**
     * Fetch record columns from query row, must use data offset to slice required part of query.
     *
     * @param int   $dataOffset
     * @param array $line
     *
     * @return array
     */
    protected function fetchData(int $dataOffset, array $line): array
    {
        //Combine column names with sliced piece of row
        return array_combine(
            $this->columns,
            array_slice($line, $dataOffset, $this->countColumns)
        );
    }

    /**
     * Create internal references cache based on requested keys. For example, if we have request for
     * "id" as reference key, every record will create following structure:
     * $this->references[id][ID_VALUE] = ITEM.
     *
     * Only deduplicated data must be collected!
     *
     * @see deduplicate()
     *
     * @param array $data
     */
    private function collectReferences(array &$data)
    {
        foreach ($this->trackReferences as $key) {
            //Adding reference(s)
            $this->references[$key][$data[$key]][] = &$data;
        }
    }

    /**
     * Create placeholders for each of sub nodes.
     *
     * @param array $data
     */
    private function ensurePlaceholders(array &$data)
    {
        //Let's force placeholders for every sub loaded
        foreach ($this->nodes as $name => $node) {
            $data[$name] = $node instanceof ArrayNode ? [] : null;
        }
    }

    /**
     * Add key to be tracked
     *
     * @param string $key
     *
     * @throws NodeException
     */
    private function trackReference(string $key)
    {
        if (!in_array($key, $this->columns)) {
            throw new NodeException("Unable to create reference, key {$key} does not exist");
        }

        if (!in_array($key, $this->trackReferences)) {
            //We are only tracking unique references
            $this->trackReferences[] = $key;
        }
    }
}