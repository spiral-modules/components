<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\Models\SchematicEntity;
use Spiral\ODM\Entities\DocumentMapper;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\Exceptions\DefinitionException;
use Spiral\ODM\Exceptions\ODMException;

/**
 * ODM component Factory.
 */
interface ODMInterface
{
    /**
     * Class definition options.
     */
    const DEFINITION         = 0;
    const DEFINITION_OPTIONS = 1;

    /**
     * Normalized document constants.
     */
    const D_DEFINITION   = self::DEFINITION;
    const D_COLLECTION   = 1;
    const D_DB           = 2;
    const D_SOURCE       = 3;
    const D_HIDDEN       = SchematicEntity::SH_HIDDEN;
    const D_SECURED      = SchematicEntity::SH_SECURED;
    const D_FILLABLE     = SchematicEntity::SH_FILLABLE;
    const D_MUTATORS     = SchematicEntity::SH_MUTATORS;
    const D_VALIDATES    = SchematicEntity::SH_VALIDATES;
    const D_DEFAULTS     = 9;
    const D_AGGREGATIONS = 10;
    const D_COMPOSITIONS = 11;

    /**
     * Get MongoDatabase instance by it's name or alias.
     *
     * @param string $name
     * @return MongoDatabase
     *
     * @throws ODMException
     */
    public function database($name = null);

    /**
     * Get given class schema or schema property. If property empty full schema array to be
     * returned.
     *
     * Example:
     * $odm->getSchema(User::class, ODM::D_COLLECTION); //User class mongo collection
     *
     * @param string $item
     * @param int    $property See property constants (all schema to be returned if value is
     *                         empty).
     *
     * @return array|mixed|string
     *
     * @throws ODMException
     */
    public function schema($item, $property = null);

    /**
     * Create instance of document by given class name and set of fields, ODM component must
     * automatically find appropriate class to be used as ODM support model inheritance.
     *
     * @todo hydrate external class type?
     *
     * @param string                $class
     * @param array                 $fields
     * @param CompositableInterface $parent
     *
     * @return DocumentEntity
     *
     * @throws DefinitionException
     */
    public function document($class, $fields, CompositableInterface $parent = null);

    /**
     * Get DocumentSelector associated with give ODM model class.
     *
     * @param string $class
     * @param array  $query
     *
     * @return DocumentSelector
     */
    public function selector($class, array $query = []);

    /**
     * Get source class associated with given ODM model class.
     *
     * @param string $class
     *
     * @return DocumentSource
     */
    public function source($class);

    /**
     * Get instance of DocumentMapper responsible for save, update and delete operations with
     * documents.
     *
     * @param string $class
     *
     * @return DocumentMapper
     */
    public function mapper($class);
}