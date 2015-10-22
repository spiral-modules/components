<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Schemas;

use Spiral\ORM\Entities\SchemaBuilder;
use Spiral\ORM\Entities\Schemas\RecordSchema;
use Spiral\ORM\Exceptions\RecordSchemaException;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Exceptions\SchemaException;

/**
 * RelationSchema is responsible for clarification of inner and outer record schemas (for example it
 * might declare required columns, indexes and foreign keys). In addition every RelationSchema must
 * pack it's definition it cachable form which will be later feeded to record Relation and will be
 * used as set of instructions
 */
interface RelationInterface
{
    /**
     * @param SchemaBuilder $builder
     * @param RecordSchema  $record
     * @param string        $name
     * @param array         $definition
     * @throws RelationSchemaException
     */
    public function __construct(
        SchemaBuilder $builder,
        RecordSchema $record,
        $name,
        array $definition
    );

    /**
     * Relation name.
     *
     * @return string
     */
    public function getName();

    /**
     * Relation type. Check ORM config to see where relation types declared.
     *
     * @return int
     */
    public function getType();

    /**
     * Check if relation has it's equivalent. For example if relation associated to a specific
     * record
     * (or, like in case with polymorphic relations to interface) it can declare alternative
     * relation definition with different relation type.
     *
     * @return bool
     */
    public function hasEquivalent();

    /**
     * Get definition for equivalent (usually polymorphic relationship) relation. For example this
     * method can route to ODM relations if outer record is instance of Document.
     *
     * @return RelationInterface
     * @throws RelationSchemaException
     * @throws RecordSchemaException
     */
    public function createEquivalent();

    /**
     * Check if relation definition contains request to be reverted. Inversion used in cases when
     * inner and outer records wants to have relations to each other.
     *
     * @return bool
     */
    public function isInversable();

    /**
     * Check if it's reasonable to create relation, creation must be skipped if outer record is
     * abstract or relation under such name already exists.
     *
     * @return bool
     */
    public function isReasonable();

    /**
     * Must declare inversed (reverted) relation in outer record schema. Relation must not be
     * created if it's name already taken.
     *
     * @throws RelationSchemaException
     * @throws SchemaException
     * @throws RelationSchemaException
     */
    public function inverseRelation();

    /**
     * Create all required relation columns, indexes and constraints.
     *
     * @throws RelationSchemaException
     * @throws \Spiral\Database\Exceptions\SchemaException
     */
    public function buildSchema();

    /**
     * Pack relation data into normalized structured to be used in cached ORM schema.
     *
     * @return array
     */
    public function normalizeSchema();
}