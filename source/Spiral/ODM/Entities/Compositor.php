<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM\Entities;

use Spiral\Core\Component;
use Spiral\ODM\CompositableInterface;
use Spiral\ODM\Document;
use Spiral\ODM\Exceptions\CompositorException;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\ODM\ODM;
use Spiral\ODM\SimpleDocument;

/**
 * Compositor is responsible for managing set (array) of classes nested to parent Document.
 * Compositor can manage class and all it's children.
 */
class Compositor extends Component implements
    CompositableInterface,
    \IteratorAggregate,
    \Countable,
    \ArrayAccess
{
    /**
     * Class being composited.
     *
     * @var string
     */
    private $class = '';

    /**
     * Set of documents to be managed by Compositor.
     *
     * @var array|SimpleDocument[]
     */
    protected $documents = [];

    /**
     * When solid state is enabled no atomic operations will be pushed to databases and document
     * composition will be saved as one big set. Enabled by default.
     *
     * @var bool
     */
    protected $solidState = true;

    /**
     * Indication that composition data were changed without using atomic operations, this flag
     * will be set to true if any document added or removed via array operations. Atomic operation
     * will be forbidden what this flag is set.
     *
     * @var bool
     */
    protected $changedDirectly = false;

    /**
     * Set of atomic operation applied to whole composition set.
     *
     * @var array
     */
    protected $atomics = [];

    /**
     * Error messages collected over nested documents.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * @invisible
     * @var SimpleDocument
     */
    protected $parent = null;

    /**
     * @var ODM
     */
    protected $odm = null;

    /**
     * {@inheritdoc}
     *
     * @param string $class Primary class being composited.
     */
    public function __construct($data, $parent = null, ODM $odm = null, $class = null)
    {
        $this->class = $class;
        $this->parent = $parent;
        if (!empty($data) && is_array($data)) {
            $this->documents = $data;
        }

        if (empty($this->class)) {
            throw new CompositorException(
                "Compositor requires to know it's primary class name."
            );
        }

        //Allowed only when global container is set
        $this->odm = !empty($odm) ? $odm : ODM::instance();

        if (empty($this->odm)) {
            throw new CompositorException(
                "ODM instance if required for Compositor to work properly."
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function defaultValue()
    {
        return [];
    }

    /**
     * Get primary compositor class.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Get composition parent.
     *
     * @return Document|null
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * When solid state is enabled no atomic operations will be pushed to databases and document
     * composition will be saved as one big set.
     *
     * @param bool $solidState
     * @return $this
     */
    public function solidState($solidState)
    {
        $this->solidState = $solidState;

        return $this;
    }

    /**
     * Is compositor is solid state?
     *
     * @see solidState()
     * @return bool
     */
    public function isSolid()
    {
        return $this->solidState;
    }

    /**
     * {@inheritdoc}
     *
     * Invalidates every composited document.
     *
     * @return $this
     */
    public function invalidate()
    {
        foreach ($this->getIterator() as $document) {
            $document->invalidate();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function embed($parent)
    {
        if (!$parent instanceof SimpleDocument) {
            throw new CompositorException("Compositor can be embedded only into Documents.");
        }

        if ($parent === $this->parent) {
            return $this;
        }

        if (empty($this->parent)) {
            $this->parent = $parent;

            //We are mounting new parent
            return $this->solidState(true);
        }

        return new static($this->serializeData(), $parent, $this->class, $this->odm);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setData($data)
    {
        $this->changedDirectly = $this->solidState = true;

        if (!is_array($data)) {
            //Ignoring
            return;
        }

        $this->documents = [];

        //Filling documents
        foreach ($data as $item) {
            if (is_array($item)) {
                $this->create($item);
            }

            if ($item instanceof CompositableInterface) {
                $this->documents[] = $item;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function serializeData()
    {
        $result = [];
        foreach ($this->documents as $document) {
            $result[] = $document instanceof CompositableInterface
                ? $document->serializeData()
                : $document;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function publicFields()
    {
        $result = [];
        foreach ($this->getIterator() as $document) {
            $result[] = $document->publicFields();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function hasUpdates()
    {
        if ($this->changedDirectly || !empty($this->atomics)) {
            return true;
        }

        foreach ($this->documents as $document) {
            if ($document instanceof CompositableInterface && $document->hasUpdates()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        $this->atomics = [];
        $this->changedDirectly = false;

        foreach ($this->documents as $document) {
            if ($document instanceof CompositableInterface) {
                $document->flushUpdates();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildAtomics($container = '')
    {
        if (!$this->hasUpdates()) {
            return [];
        }

        if ($this->solidState) {
            return [Document::ATOMIC_SET => [$container => $this->serializeData()]];
        }

        if ($this->changedDirectly) {
            throw new CompositorException(
                "Compositor data were changed with low level array manipulations, "
                . "unable to generate atomic set (solid state off)."
            );
        }

        $atomics = [];

        //Documents handled by Compositor atomic operations
        $handledDocuments = [];

        foreach ($this->atomics as $operation => $items) {
            if ($operation != '$pull') {
                $handledDocuments = array_merge($handledDocuments, $items);
            }

            //Into array form
            $atomics[$operation][$container] = $this->serializeDocuments($items);
        }

        //Document specific atomic operations, make sure it's not colliding with Compositor level
        //atomic operations
        foreach ($this->documents as $offset => $document) {
            if ($document instanceof CompositableInterface) {
                if (in_array($document, $handledDocuments)) {
                    //Handler on higher level
                    continue;
                }

                $atomics = array_merge(
                    $atomics,
                    $document->buildAtomics(($container ? $container . '.' : '') . $offset)
                );
            }
        }

        return $atomics;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->documents);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->documents[$offset]);
    }

    /**
     * {@inheritdoc}
     * @throws CompositorException
     */
    public function offsetGet($offset)
    {
        if (!isset($this->documents[$offset])) {
            throw new CompositorException("Undefined offset '{$offset}'.");
        }

        return $this->getDocument($offset);
    }

    /**
     * {@inheritdoc}
     * @throws CompositorException
     */
    public function offsetSet($offset, $value)
    {
        if (!$value instanceof CompositableInterface) {
            throw new CompositorException("Compositor can contain only instances of Document.");
        }

        if (!$this->solidState) {
            throw new CompositorException(
                "Direct offset operation can not be applied for compositor in non solid state."
            );
        }

        $this->changedDirectly = true;
        if (is_null($offset)) {
            $this->documents[] = $value;
        } else {
            $this->documents[$offset] = $value;
        }
    }

    /**
     * {@inheritdoc}
     * @throws CompositorException
     */
    public function offsetUnset($offset)
    {
        if (!$this->solidState) {
            throw new CompositorException(
                "Direct offset operation can not be applied for compositor in non solid state."
            );
        }

        $this->changedDirectly = true;
        unset($this->documents[$offset]);
    }

    /**
     * {@inheritdoc}
     *
     * @return Document[]
     */
    public function getIterator()
    {
        foreach ($this->documents as $offset => $document) {
            $this->getDocument($offset);
        }

        return new \ArrayIterator($this->documents);
    }

    /**
     * Create Document and add it to composition. Compositor will use it's primary class to
     * construct document. You can* force custom class name to be added using second argument.
     *
     * @param array  $fields
     * @param string $class
     * @return Document
     * @throws CompositorException
     * @throws DefinitionException
     * @throws ODMException
     */
    public function create(array $fields = [], $class = null)
    {
        if (!$this->solidState) {
            throw new CompositorException(
                "Direct offset operation can not be applied for compositor in non solid state."
            );
        }

        //Locating class to be used
        $class = !empty($class) ? $class : $this->class;

        $this->changedDirectly = true;
        $this->documents[] = $document = call_user_func([$class, 'create'], $fields,
            $this->odm)->embed($this);

        return $document;
    }

    /**
     * Clear all nested documents.
     *
     * @return $this
     */
    public function clear()
    {
        $this->solidState = $this->changedDirectly = true;
        $this->documents = [];

        return $this;
    }


    /**
     * Find documents based on provided field values or document instance. Only simple query support
     * (one level array).
     *
     * Example:
     * $user->cards->find(['active' => true]);
     *
     * @param array|SimpleDocument $query
     * @return array|SimpleDocument[]
     */
    public function find($query = [])
    {
        if ($query instanceof Document) {
            $query = $query->serializeData();
        }

        $result = [];
        foreach ($this->documents as $offset => $document) {

            //We have to pass document thought model construction to ensure default values
            $document = $this->getDocument($offset);

            $data = $document->serializeData();
            if (empty($query) || (array_intersect_assoc($data, $query) == $query)) {
                $result[] = $document;
            }
        }

        return $result;
    }

    /**
     * Find first composited (nested document) by matched query. Only simple query support (one
     * level array).
     *
     * Example:
     * $user->cards->findOne(['active' => true]);
     *
     * @param array|SimpleDocument $query
     * @return null|SimpleDocument
     */
    public function findOne($query = [])
    {
        if (empty($documents = $this->find($query))) {
            return null;
        }

        return $documents[0];
    }

    /**
     * Push new document to end of set. Set second argument to false to keep Compositor in solid
     * state and save it as one big array of data.
     *
     * @param SimpleDocument $document
     * @param bool           $resetState Set to true to reset compositor solid state.
     * @return $this|SimpleDocument[]
     * @throws CompositorException
     */
    public function push(SimpleDocument $document, $resetState = true)
    {
        if ($resetState) {
            $this->solidState = false;
        }

        $this->documents[] = $document->embed($this);

        if ($this->solidState) {
            $this->changedDirectly = true;

            return $this;
        }

        if (!empty($this->atomics) && !isset($this->atomics['$push'])) {
            throw new CompositorException(
                "Unable to apply multiple atomic operation to one Compositor."
            );
        }

        $this->atomics['$push'][] = $document;

        return $this;
    }

    /**
     * Pulls document(s) from the set, query should represent document object matched fields. Set
     * second argument to false to keep Compositor in solid state and save it as one big array of
     * data.
     *
     * @param array|SimpleDocument $query
     * @param bool                 $resetState Set to true to reset compositor solid state.
     * @return $this|SimpleDocument[]
     * @throws CompositorException
     */
    public function pull($query, $resetState = true)
    {
        if ($resetState) {
            $this->solidState = false;
        }

        if ($query instanceof SimpleDocument) {
            $query = $query->serializeData();
        }

        foreach ($this->documents as $offset => $document) {
            //We have to pass document thought model construction to ensure default values
            $document = $this->getDocument($offset)->serializeData();

            if (array_intersect_assoc($document, $query) == $query) {
                unset($this->documents[$offset]);
            }
        }

        if ($this->solidState) {
            $this->changedDirectly = true;

            return $this;
        }

        if (!empty($this->atomics) && !isset($this->atomics['$pull'])) {
            throw new CompositorException(
                "Unable to apply multiple atomic operation to composition."
            );
        }

        $this->atomics['$pull'][] = $query;

        return $this;
    }

    /**
     * Add document to set, only one instance of document must be presented. Set second argument to
     * false to keep Compositor in solid state and save it as one big array of data.
     *
     * @param SimpleDocument $document
     * @param bool           $resetState Set to true to reset compositor solid state.
     * @return $this|SimpleDocument[]
     * @throws CompositorException
     */
    public function addToSet(SimpleDocument $document, $resetState = true)
    {
        if ($resetState) {
            $this->solidState = false;
        }

        if (empty($this->findOne($document))) {
            $this->documents[] = $document->embed($this);
        }

        if ($this->solidState) {
            $this->changedDirectly = true;

            return $this;
        }

        if (!empty($this->atomics) && !isset($this->atomics['$addToSet'])) {
            throw new CompositorException(
                "Unable to apply multiple atomic operation to composition."
            );
        }

        $this->atomics['$addToSet'][] = $document;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        $this->validate();

        return empty($this->errors);
    }

    /**
     * {@inheritdoc}
     */
    public function hasErrors()
    {
        return !$this->isValid();
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($reset = false)
    {
        $errors = $this->errors;

        if ($reset) {
            $this->errors = [];
        }

        return $errors;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->publicFields();
    }

    /**
     * @return Object
     */
    public function __debugInfo()
    {
        $this->validate();

        return (object)[
            'data'    => $this->serializeData(),
            'atomics' => $this->buildAtomics('@compositor'),
            'errors'  => $this->getErrors()
        ];
    }

    /**
     * Validate every composited document.
     *
     * @return bool
     */
    protected function validate()
    {
        $this->errors = [];
        foreach ($this->documents as $offset => $document) {
            $document = $this->getDocument($offset);

            if (!$document->isValid()) {
                $this->errors[$offset] = $document->getErrors();
            }
        }

        return empty($this->errors);
    }

    /**
     * Fetch or create instance of document based on specified offset.
     *
     * @param int $offset
     * @return SimpleDocument
     */
    private function getDocument($offset)
    {
        /**
         * @var array|Document $document
         */
        $document = $this->documents[$offset];
        if ($document instanceof CompositableInterface) {
            return $document;
        }

        //Trying to create using ODM
        return $this->documents[$offset] = $this->odm->document($this->class, $document, $this);
    }

    /**
     * Serialize array of documents into simple array.
     *
     * @param array $documents
     * @return array
     */
    private function serializeDocuments(array $documents)
    {
        $result = [];
        foreach ($documents as $document) {
            if ($document instanceof CompositableInterface) {
                $document = $document->serializeData();
            }
            $result[] = $document;
        }

        return $result;
    }
}