<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Schemas;

use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ODM\Exceptions\DefinitionException;

/**
 * Helps to define sequence of fields needed to clearly distinguish one model from another based on
 * given data.
 */
class InheritanceHelper
{
    /**
     * @var DocumentSchema
     */
    private $schema;

    /**
     * All other registered schemas.
     *
     * @var SchemaInterface[]
     */
    private $schemas = [];

    /**
     * @param DocumentSchema    $schema
     * @param SchemaInterface[] $schemas
     */
    public function __construct(DocumentSchema $schema, array $schemas)
    {
        $this->schema = $schema;
        $this->schemas = $schemas;
    }

    /**
     * Compile information required to resolve class instance using given set of fields. Fields
     * based definition will analyze unique fields in every child model to create association
     * between model class and required set of fields. Only document from same collection will be
     * involved in definition creation. Definition built only for child of first order.
     *
     * @return array|string
     *
     * @throws DefinitionException
     */
    public function makeDefinition()
    {
        //Find only first level children stored in the same collection
        $children = $this->findChildren(true, true);

        if (empty($children)) {
            //Nothing to inherit
            return $this->schema->getClass();
        }

        //We must sort child in order or unique fields
        uasort($children, [$this, 'sortChildren']);

        //Fields which are common for parent and child models
        $commonFields = $this->schema->getReflection()->getFields();

        $definition = [];
        foreach ($children as $schema) {
            //Child document fields
            $fields = $schema->getReflection()->getFields();

            if (empty($fields)) {
                throw new DefinitionException(
                    "Child document '{$schema->getClass()}' of {$this->schema->getClass()} does not have any fields"
                );
            }

            $uniqueField = null;
            if (empty($commonFields)) {
                //Parent did not declare any fields, happen sometimes
                $commonFields = $fields;
                $uniqueField = key($fields);
            } else {
                foreach ($fields as $field => $type) {
                    if (!isset($commonFields[$field])) {
                        if (empty($uniqueField)) {
                            $uniqueField = $field;
                        }

                        //New non unique field (must be excluded from analysis)
                        $commonFields[$field] = true;
                    }
                }
            }

            if (empty($uniqueField)) {
                throw new DefinitionException(
                    "Child document {$schema} of {$this} does not have any unique field"
                );
            }

            $definition[$uniqueField] = $schema->getClass();
        }

        return $definition;
    }

    /**
     * Get Document child classes.
     *
     * Example:
     * Class A
     * Class B extends A
     * Class D extends A
     * Class E extends D
     *
     * Result: B, D, E
     *
     * @see getPrimary()
     *
     * @param bool $sameCollection Find only children related to same collection as parent.
     * @param bool $directChildren Only child extended directly from current document.
     *
     * @return DocumentSchema[]
     */
    public function findChildren(bool $sameCollection = false, bool $directChildren = false)
    {
        $result = [];
        foreach ($this->schemas as $schema) {
            //Only Document and DocumentEntity classes supported
            if (!$schema instanceof DocumentSchema) {
                continue;
            }

            //ReflectionEntity
            $reflection = $schema->getReflection();

            if ($reflection->isSubclassOf($this->schema->getClass())) {

                if ($sameCollection && !$this->compareCollection($schema)) {
                    //Child changed collection or database
                    continue;
                }

                if (
                    $directChildren
                    && $reflection->getParentClass()->getName() != $this->schema->getClass()
                ) {
                    //Grandson
                    continue;
                }

                $result[] = $schema;
            }
        }

        return $result;
    }

    /**
     * Find primary class needed to represent model and model childs.
     *
     * @param bool $sameCollection Find only parent related to same collection as model.
     *
     * @return string
     */
    public function findPrimary(bool $sameCollection = true): string
    {
        $primary = $this->schema->getClass();
        foreach ($this->schemas as $schema) {
            //Only Document and DocumentEntity classes supported
            if (!$schema instanceof DocumentSchema) {
                continue;
            }

            if ($this->schema->getReflection()->isSubclassOf($schema->getClass())) {
                if ($sameCollection && !$this->compareCollection($schema)) {
                    //Child changed collection or database
                    continue;
                }

                $primary = $schema->getClass();
            }
        }

        return $primary;
    }

    /**
     * @return ReflectionEntity
     */
    protected function getReflection(): ReflectionEntity
    {
        return $this->schema->getReflection();
    }

    /**
     * Check if both document schemas belongs to same collection. Documents without declared
     * collection must be counted as documents from same collection.
     *
     * @param DocumentSchema $document
     *
     * @return bool
     */
    protected function compareCollection(DocumentSchema $document)
    {
        if ($document->getDatabase() != $this->schema->getDatabase()) {
            return false;
        }

        return $document->getCollection() == $this->schema->getCollection();
    }

    /**
     * Sort child documents in order or declared fields.
     *
     * @param DocumentSchema $childA
     * @param DocumentSchema $childB
     *
     * @return int
     */
    private function sortChildren(DocumentSchema $childA, DocumentSchema $childB)
    {
        return count($childA->getReflection()->getFields()) > count($childB->getReflection()->getFields());
    }
}