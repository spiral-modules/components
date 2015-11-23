<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

use Interop\Container\ContainerInterface;
use Spiral\Core\Component;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\EntityInterface;
use Spiral\ODM\CompositableInterface;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Exceptions\CompositorException;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\ODM\ODM;

/**
 * Associative array of documents.
 */
class Hash extends Component implements CompositableInterface, \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * Optional arguments.
     */
    use SaturateTrait;

    /**
     * Class being composited.
     *
     * @var string
     */
    private $class = '';

    /**
     * When solid state is enabled no atomic operations will be pushed to databases and document
     * composition will be saved as one big set. Enabled by default.
     *
     * @var bool
     */
    private $solidState = true;

    /**
     * Indication that composition data were changed without using atomic operations, this flag
     * will be set to true if any document added or removed via array operations. Atomic operation
     * will be forbidden what this flag is set.
     *
     * @var bool
     */
    private $changedDirectly = false;

    /**
     * Set of documents to be managed by Compositor.
     *
     * @var array|DocumentEntity[]
     */
    protected $documents = [];

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
     * @var EntityInterface
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
    public function __construct(
        $value,
        EntityInterface $parent = null,
        ODM $odm = null,
        $class = null
    ) {
        $this->parent = $parent;
        $this->class = $class;

        if (!empty($value) && is_array($value)) {
            $this->documents = $value;
        }

        if (empty($this->class)) {
            throw new CompositorException("Compositor requires to know it's primary class name.");
        }

        //Allowed only when global container is set
        $this->odm = $this->saturate($odm, ODM::class);

        if (empty($this->odm)) {
            throw new CompositorException("ODM instance if required for Compositor to work properly.");
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
     * @return DocumentEntity|null
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
    public function embed(EntityInterface $parent)
    {
        if ($parent === $this->parent) {
            return $this;
        }

        if (empty($this->parent)) {
            $this->parent = $parent;

            //We are mounting new parent
            return $this->solidState(true);
        }

        return new static(
            $this->serializeData(),
            $parent,
            $this->class,
            $this->odm
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function setValue($data)
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
        return $this->serializeDocuments($this->documents);
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
            return [DocumentEntity::ATOMIC_SET => [$container => $this->serializeData()]];
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
            $atomics[$operation][$container]['$each'] = $this->serializeDocuments($items);
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
     */
    public function hasField($name)
    {
        return $this->offsetExists($name);
    }

    /**
     * {@inheritdoc}
     */
    public function setField($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @throws CompositorException
     */
    public function getField($name, $default = null)
    {
        if (!$this->hasField($name)) {
            return $default;
        }

        return $this->offsetGet($name);
    }

    /**
     * {@inheritdoc}
     */
    public function setFields($fields = [])
    {
        $this->setValue($fields);
    }

    /**
     * {@inheritdoc}
     */
    public function getFields()
    {
        return $this->all();
    }

    /**
     * Return all composited documents in array form.
     *
     * @return DocumentEntity[]
     */
    public function all()
    {
        $result = [];
        foreach ($this->getIterator() as $document) {
            $result[] = $document;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @return DocumentEntity[]
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
     * @return DocumentEntity
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

        $document = call_user_func([$class, 'create'], $fields, $this->odm)->embed($this);
        $this->documents[] = $document;

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
     * @param array|DocumentEntity $query
     * @return array|DocumentEntity[]
     */
    public function find($query = [])
    {
        if ($query instanceof DocumentEntity) {
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
     * @param array|DocumentEntity $query
     * @return null|DocumentEntity
     */
    public function findOne($query = [])
    {
        if (empty($documents = $this->find($query))) {
            return null;
        }

        return $documents[0];
    }

    /**
     * @param string         $key
     * @param DocumentEntity $document
     * @param bool           $resetState Set to true to reset compositor solid state.
     * @return $this|DocumentEntity[]
     * @throws CompositorException
     */
    public function set($key, DocumentEntity $document, $resetState = true)
    {
        if ($resetState) {
            $this->solidState = false;
        }

        $this->documents[$key] = $document->embed($this);

        if ($this->solidState) {
            $this->changedDirectly = true;

            return $this;
        }

        if (!empty($this->atomics) && !isset($this->atomics['$set'])) {
            throw new CompositorException(
                "Unable to apply multiple atomic operation to one Compositor."
            );
        }

        $this->atomics['$set'][$key] = $document;

        return $this;
    }

    /**
     *
     * @param string $key        Remove document associated with given key.
     * @param bool   $resetState Set to true to reset compositor solid state.
     * @return $this|DocumentEntity[]
     * @throws CompositorException
     */
    public function remove($key, $resetState = true)
    {
        if ($resetState) {
            $this->solidState = false;
        }

        unset($this->documents[$key]);

        if ($this->solidState) {
            $this->changedDirectly = true;

            return $this;
        }

        if (!empty($this->atomics) && !isset($this->atomics['$unset'])) {
            throw new CompositorException(
                "Unable to apply multiple atomic operation to composition."
            );
        }

        $this->atomics['$unset'][] = $key;

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
     * @return null|ContainerInterface
     */
    protected function container()
    {
        if (!empty($this->parent) && $this->parent instanceof Component) {
            return $this->parent->container();
        }

        return parent::container();
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
            //To ensure that instance is Document since components
            //is lazy loading them
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
     * @return DocumentEntity
     */
    private function getDocument($offset)
    {
        /**
         * @var array|DocumentEntity $document
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
        foreach ($documents as $key => $document) {
            if ($document instanceof CompositableInterface) {
                $document = $document->serializeData();
            }
            $result[$key] = $document;
        }

        return $result;
    }
}