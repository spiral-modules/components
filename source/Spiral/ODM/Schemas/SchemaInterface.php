<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Schemas;

use Spiral\ODM\Exceptions\SchemaException;
use Spiral\ODM\Schemas\Definitions\IndexDefinition;

/**
 * Describes document or class schema to be cached on ODM memory.
 */
interface SchemaInterface
{
    /**
     * Related class name.
     *
     * @return string
     */
    public function getClass(): string;

    /**
     * Name of class responsible for model instantiation.
     *
     * @return string
     */
    public function getInstantiator(): string;

    /**
     * Indication that document is embeddable.
     *
     * @return bool
     */
    public function isEmbedded(): bool;

    /**
     * Name of associated database. Might return null to force default database usage.
     *
     * @return string|null
     *
     * @throws SchemaException
     */
    public function getDatabase();

    /**
     * Name of associated collection.
     *
     * @return string
     *
     * @throws SchemaException
     */
    public function getCollection(): string;

    /**
     * Get list of indexes to defined in associated collection.
     *
     * @return IndexDefinition[]|\Generator
     *
     * @throws SchemaException
     */
    public function getIndexes();

    /**
     * Since ODM support inheritance some model collections will be related to parent model, rather
     * than child model. Attention, primary class MUST be related to the same collection as child.
     *
     * @param SchemaBuilder $builder
     *
     * @return string
     *
     * @throws SchemaException
     */
    public function resolvePrimary(SchemaBuilder $builder): string;

    /**
     * Pack schema in a form compatible with entity class and selected mapper.
     *
     * @param SchemaBuilder $builder
     *
     * @return array
     *
     * @throws SchemaException
     */
    public function packSchema(SchemaBuilder $builder): array;
}