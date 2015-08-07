<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM;

interface DocumentAccessorInterface extends CompositableInterface
{
    /**
     * Accessor default value.
     *
     * @return mixed
     */
    public function defaultValue();
}