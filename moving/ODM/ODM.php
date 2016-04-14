<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ODM;

use Spiral\Core\Container\SingletonInterface;
use Spiral\Models\DataEntity;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\Entities\SchemaBuilder;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;
use Spiral\Tokenizer\ClassLocatorInterface;

/**
 * ODM component used to manage state of cached Document's schema, document creation and schema
 * analysis.
 */
class ODM extends MongoManager implements SingletonInterface
{
    /**
     * Create instance of document by given class name and set of fields, ODM component must
     * automatically find appropriate class to be used as ODM support model inheritance.
     *
     * @todo hydrate external class type!
     *
     * @param string                $class
     * @param array                 $fields
     * @param CompositableInterface $parent
     *
     * @return Document
     *
     * @throws DefinitionException
     */
    public function document($class, $fields, CompositableInterface $parent = null)
    {
        $class = $this->defineClass($class, $fields, $schema);

        return new $class($fields, $parent, $this, $schema);
    }

    /**
     * Get instance of ODM source associated with given model class.
     *
     * @param string $class
     *
     * @return DocumentSource
     */
    public function source($class)
    {
        $schema = $this->schema($class);
        if (empty($source = $schema[self::D_SOURCE])) {
            $source = DocumentSource::class;
        }

        return new $source($class, $this);
    }

    /**
     * Get instance of ODM Selector associated with given class.
     *
     * @param       $class
     * @param array $query
     *
     * @return DocumentSelector
     */
    public function selector($class, array $query = [])
    {
        $schema = $this->schema($class);

        return new DocumentSelector(
            $this,
            $schema[self::D_DB],
            $schema[self::D_COLLECTION],
            $query
        );
    }

    /**
     * Define document class using it's fieldset and definition.
     *
     * @see Document::DEFINITION
     *
     * @param string $class
     * @param array  $fields
     * @param array  $schema Found class schema, reference.
     *
     * @return string
     *
     * @throws DefinitionException
     */
    public function defineClass($class, $fields, &$schema = [])
    {
        $schema = $this->schema($class);

        $definition = $schema[self::D_DEFINITION];
        if (is_string($definition)) {
            //Document has no variations
            return $definition;
        }

        if (!is_array($fields)) {
            //Unable to resolve
            return $class;
        }

        $defined = $class;
        if ($definition[self::DEFINITION] == DocumentEntity::DEFINITION_LOGICAL) {

            //Resolve using logic function
            $defined = call_user_func($definition[self::DEFINITION_OPTIONS], $fields, $this);

            if (empty($defined)) {
                throw new DefinitionException(
                    "Unable to resolve (logical definition) valid class for document '{$class}'."
                );
            }
        } elseif ($definition[self::DEFINITION] == DocumentEntity::DEFINITION_FIELDS) {
            foreach ($definition[self::DEFINITION_OPTIONS] as $field => $child) {
                if (array_key_exists($field, $fields)) {
                    //Apparently this is child
                    $defined = $child;
                    break;
                }
            }
        }

        //Child may change definition method or declare it's own children
        return $defined == $class ? $class : $this->defineClass($defined, $fields, $schema);
    }

    /**
     * Get cached schema data by it's item name (document name, collection name).
     *
     * @param string $item
     *
     * @return array|string
     *
     * @throws ODMException
     */
    public function schema($item)
    {
        if (!isset($this->schema[$item])) {
            $this->updateSchema();
        }

        if (!isset($this->schema[$item])) {
            throw new ODMException("Undefined ODM schema item '{$item}'.");
        }

        return $this->schema[$item];
    }

    /**
     * Get primary document class to be associated with collection. Attention, collection may return
     * parent document instance even if query was made using children implementation.
     *
     * @param string $database
     * @param string $collection
     *
     * @return string
     */
    public function primaryDocument($database, $collection)
    {
        return $this->schema($database . '/' . $collection);
    }

    /**
     * Update ODM documents schema and return instance of SchemaBuilder.
     *
     * @param SchemaBuilder $builder User specified schema builder.
     * @param bool          $createIndexes
     *
     * @return SchemaBuilder
     */
    public function updateSchema(SchemaBuilder $builder = null, $createIndexes = false)
    {
        if (empty($builder)) {
            $builder = $this->schemaBuilder();
        }

        //We will create all required indexes now
        if ($createIndexes) {
            $builder->createIndexes();
        }

        //Getting cached/normalized schema
        $this->schema = $builder->normalizeSchema();

        //Saving
        $this->memory->saveData(static::MEMORY, $this->schema);

        //Let's reinitialize models
        DataEntity::resetInitiated();

        return $builder;
    }

    /**
     * Get instance of ODM SchemaBuilder.
     *
     * @param ClassLocatorInterface $locator
     *
     * @return SchemaBuilder
     */
    public function schemaBuilder(ClassLocatorInterface $locator = null)
    {
        return $this->factory->make(SchemaBuilder::class, [
            'odm'     => $this,
            'config'  => $this->config['schemas'],
            'locator' => $locator,
        ]);
    }
}
