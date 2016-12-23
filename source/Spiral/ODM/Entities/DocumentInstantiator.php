<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Entities;

use Spiral\ODM\CompositableInterface;
use Spiral\ODM\Document;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\InstantiatorInterface;
use Spiral\ODM\ODMInterface;

/**
 * Provides ability to construct Document and DocumentEntities with inheritance support.
 */
class DocumentInstantiator implements InstantiatorInterface
{
    /**
     * @invisible
     * @var ODMInterface
     */
    private $odm;

    /**
     * Primary instantiation class.
     *
     * @var string
     */
    private $class = '';

    /**
     * Normalized schema delivered by DocumentSchema.
     *
     * @var array
     */
    private $schema = [];

    /**
     * @param ODMInterface $odm
     * @param string       $class
     * @param array        $schema
     */
    public function __construct(ODMInterface $odm, string $class, array $schema)
    {
        $this->odm = $odm;
        $this->class = $class;
        $this->schema = $schema;
    }

    /**
     * @param array|\ArrayAccess $fields
     *
     * @return CompositableInterface|DocumentEntity|Document
     */
    public function instantiate($fields): CompositableInterface
    {
        $class = $this->defineClass($fields);

        if ($class !== $this->class) {
            //We have to dedicate class creation to external instantiator (possibly children class)
            return $this->odm->instantiate($class, $fields);
        }

        //Now we can construct needed class, in this case we are following DocumentEntity declaration
        return new $class($fields, $this->schema, $this->odm);
    }

    /**
     * Define document class using it's fieldset and definition.
     *
     * @param \ArrayAccess|array $fields
     *
     * @return string
     *
     * @throws DefinitionException
     */
    protected function defineClass($fields)
    {
        //Rule to define class instance
        $definition = $this->schema[DocumentEntity::SH_INSTANTIATION];

        if (is_string($definition)) {
            //Document has no variations
            return $definition;
        }

        if (!is_array($fields)) {
            //Unable to resolve for non array set, using same class as given
            return $this->class;
        }

        $defined = $this->class;
        foreach ($definition as $field => $child) {
            if (array_key_exists($field, $fields)) {
                //Apparently this is child
                $defined = $child;
                break;
            }
        }

        return $defined;
    }
}