<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Storage\Entities;

use Psr\Http\Message\StreamInterface;
use Spiral\Core\Component;
use Spiral\Core\Exceptions\ScopeException;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Exceptions\ObjectException;
use Spiral\Storage\ObjectInterface;
use Spiral\Storage\StorageInterface;

/**
 * Default implementation of storage object. This is immutable class.
 */
class StorageObject extends Component implements ObjectInterface
{
    use SaturateTrait;

    /**
     * @var BucketInterface
     */
    private $bucket = null;

    /**
     * @var string
     */
    private $address = false;

    /**
     * @var string
     */
    private $name = false;

    /**
     * @invisible
     * @var StorageInterface
     */
    protected $storage = null;

    /**
     * @param string                $address
     * @param StorageInterface|null $storage
     *
     * @throws ScopeException
     */
    public function __construct(string $address, StorageInterface $storage = null)
    {
        $this->storage = $this->saturate($storage, StorageInterface::class);

        //Trying to find bucket using address
        if (empty($address)) {
            throw new ObjectException("Unable to create StorageObject with empty address");
        }

        $this->address = $address;
        $this->bucket = $this->storage->locateBucket($address, $this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * {@inheritdoc}
     */
    public function getBucket(): BucketInterface
    {
        return $this->bucket;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(): bool
    {
        if (empty($this->name)) {
            return false;
        }

        return $this->bucket->exists($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        if (empty($this->name)) {
            return false;
        }

        return $this->bucket->size($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function localFilename(): string
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to allocate filename for unassigned storage object");
        }

        return $this->bucket->allocateFilename($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function getStream(): StreamInterface
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to get stream for unassigned storage object");
        }

        return $this->bucket->allocateStream($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function delete()
    {
        if (empty($this->name)) {
            return;
        }

        $this->bucket->delete($this->name);

        $this->address = $this->name = '';
        $this->bucket = null;
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $newName): ObjectInterface
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to rename unassigned storage object");
        }

        $this->address = $this->bucket->rename($this->name, $newName);
        $this->name = $newName;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $destination): ObjectInterface
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to copy unassigned storage object");
        }

        if (is_string($destination)) {
            $destination = $this->storage->getBucket($destination);
        }

        return $this->storage->open($this->bucket->copy($destination, $this->name));
    }

    /**
     * {@inheritdoc}
     */
    public function replace(string $destination): ObjectInterface
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to replace unassigned storage object");
        }

        if (is_string($destination)) {
            $destination = $this->storage->getBucket($destination);
        }

        $this->address = $this->bucket->replace($destination, $this->name);
        $this->bucket = $destination;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->address;
    }
}