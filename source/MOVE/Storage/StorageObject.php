<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Storage;

class StorageObject implements ObjectInterface
{
    /**
     * Full object address. Address used to identify associated bucket using bucket prefix,
     * address can be either meaningless string or be valid URL, in this case object address can be
     * used as to detect bucket, as to show on web page.
     *
     * @var string
     */
    protected $address = false;

    /**
     * Storage component.
     *
     * @invisible
     * @var StorageInterface
     */
    protected $storage = null;

    /**
     * Associated storage bucket. Every bucket represent one "virtual" folder which can be
     * located on local machine, another server (ftp) or in cloud (amazon, rackspace). bucket
     * provides basic unified functionality to manage files inside, all low level operations perform
     * by servers (adapters), this technique allows you to create application and code which does not
     * require to specify storage requirements at time of development.
     *
     * @var BucketInterface
     */
    protected $bucket = null;

    /**
     * Object name is relative name inside one specific bucket, can include filename and directory
     * name.
     *
     * @var string
     */
    protected $name = false;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        $address,
        $name = '',
        StorageInterface $storage,
        BucketInterface $bucket = null
    )
    {
        $this->storage = $storage;

        if (!empty($bucket))
        {
            //We already know address and name
            $this->address = $address;
            $this->bucket = $bucket;
            $this->name = $name;

            return;
        }

        //Trying to find bucket using address
        if (empty($address))
        {
            throw new StorageException("Unable to create StorageObject with empty address.");
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
        if (empty($this->name))
        {
            return false;
        }

        return $this->bucket->exists($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        if (empty($this->name))
        {
            return false;
        }

        return $this->bucket->size($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function localFilename()
    {
        if (empty($this->name))
        {
            throw new StorageException("Unable to allocate filename for unassigned storage object.");
        }

        return $this->bucket->allocateFilename($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function getStream()
    {
        if (empty($this->name))
        {
            throw new StorageException("Unable to get stream for unassigned storage object.");
        }

        return $this->bucket->allocateStream($this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function delete()
    {
        if (empty($this->name))
        {
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
        if (empty($this->name))
        {
            throw new StorageException("Unable to rename unassigned storage object.");
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
        if (empty($this->name))
        {
            throw new StorageException("Unable to copy unassigned storage object.");
        }

        if (is_string($destination))
        {
            $destination = $this->storage->bucket($destination);
        }

        return $this->bucket->copy($destination, $this->name);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($destination)
    {
        if (empty($this->name))
        {
            throw new StorageException("Unable to replace unassigned storage object.");
        }

        if (is_string($destination))
        {
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