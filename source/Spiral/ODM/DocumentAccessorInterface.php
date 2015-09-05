<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM;

use Spiral\ODM\Exceptions\AccessorException;

/**
 * Declares requirement for every ODM field accessor to be an instance of EmbeddableInterface and
 * declare it's default value. In addition constructor is unified and no container used to create
 * accessors, however accessor still can resolve it's dependencies using getContainer() method of
 * ODM component which must always be provided by parent document.
 *
 * Parent model will not be supplied to accessor while schema analysis!
 */
interface DocumentAccessorInterface extends EmbeddableInterface
{
    /**
     * {@inheritdoc}
     *
     * Accessor options include field type resolved by DocumentSchema.
     *
     * @param ODM   $odm     ODM component.
     * @param mixed $options Implementation specific options. In ODM will always contain field type.
     * @throws AccessorException
     */
    public function __construct($data, $parent = null, ODM $odm = null, $options = null);

    /**
     * Accessor default value.
     *
     * @return mixed
     */
    public function defaultValue();
}