<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\ORM\Exceptions\LoaderException;

/**
 * Loaders provide ability to create data tree based on set of nested queries or parse resulted
 * rows to properly link child data into valid place.
 */
interface LoaderInterface
{
    /**
     * Mount parent for a given loader, parent will declare TreeParser where loader can mount his
     * data, in addition each loader will declare set of fields to be aggregated in a parent and
     * used to properly load connected data (AbstractLoaders can also be loaded directly thought
     * joining into SQL query).
     *
     * @param LoaderInterface $parent
     *
     * @return LoaderInterface
     *
     * @throws LoaderException
     */
    public function withParent(LoaderInterface $parent): self;

    /**
     * Create version of loader with new set of load options (relation specific).
     *
     * @param array $options
     *
     * @return LoaderInterface
     *
     * @throws LoaderException
     */
    public function withOptions(array $options): self;
}