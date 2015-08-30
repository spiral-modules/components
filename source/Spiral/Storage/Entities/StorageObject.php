<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Storage\Entities;

use Spiral\Storage\BucketInterface;
use Spiral\Storage\Exceptions\ObjectException;
use Spiral\Storage\ObjectInterface;
use Spiral\Storage\StorageInterface;

/**
 * Default implementation of storage object.
 */
class StorageObject implements ObjectInterface
{
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
     * {@inheritdoc}
     */
    public function __construct($address, StorageInterface $storage)
    {
        $this->storage = $storage;

        //Trying to find bucket using address
        if (empty($address)) {
            throw new ObjectException("Unable to create StorageObject with empty address.");
        }

        $this->address = $address;
        $this->bucket = $this->storage->locateBucket($address, $this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * {@inheritdoc}
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * {@inheritdoc}
     */
    public function exists()
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
    public function localFilename()
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to allocate filename for unassigned storage object.");
        }

        return $this->bucket->allocateFilename($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function getStream()
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to get stream for unassigned storage object.");
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
    public function rename($newname)
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to rename unassigned storage object.");
        }

        $this->address = $this->bucket->rename($this->name, $newname);
        $this->name = $newname;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($destination)
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to copy unassigned storage object.");
        }

        if (is_string($destination)) {
            $destination = $this->storage->bucket($destination);
        }

        return $this->storage->open($this->bucket->copy($destination, $this->name));
    }

    /**
     * {@inheritdoc}
     */
    public function replace($destination)
    {
        if (empty($this->name)) {
            throw new ObjectException("Unable to replace unassigned storage object.");
        }

        if (is_string($destination)) {
            $destination = $this->storage->bucket($destination);
        }

        $this->address = $this->bucket->replace($destination, $this->name);
        $this->bucket = $destination;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->address;
    }
}