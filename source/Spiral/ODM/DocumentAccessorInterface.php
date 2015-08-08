<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM;

/**
 * Declares requirement for every ODM field accessor to be an instance of EmbeddableInterface and declare it's default
 * value. In addition construction is unified for every accessor.
 */
interface DocumentAccessorInterface extends EmbeddableInterface
{
    /**
     * {@inheritdoc}
     *
     * @param mixed $options Implementation specific options.
     * @param ODM   $odm     ODM component.
     */
    public function __construct($data, $parent, $options = null, ODM $odm = null);

    /**
     * Accessor default value.
     *
     * @return mixed
     */
    public function defaultValue();
}