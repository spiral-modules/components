<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ODM;

interface ODMInterface
{
    /**
     * Constants used in packed schema.
     */
    const D_INSTANTIATOR = 0;
    const D_SCHEMA       = 1;
    const D_DATABASE     = 2;
    const D_COLLECTION   = 3;

    /**
     * Get instance manager associated with given class.
     *
     * @todo do some shit here!
     *
     * @param string $class
     *
     * @return InstantiatorInterface
     */
    public function instantiator(string $class): InstantiatorInterface;
}