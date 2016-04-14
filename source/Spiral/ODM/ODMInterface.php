<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\ODM\Entities\MongoDatabase;
use Spiral\ODM\Exceptions\DefinitionException;

/**
 * ODM component Factory.
 */
interface ODMInterface
{
    /**
     * Get mongo database by it's name or alias.
     *
     * @param string $database
     * @return MongoDatabase
     */
    public function database($database);

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
     * @return DocumentEntity
     *
     * @throws DefinitionException
     */
    public function document($class, $fields, CompositableInterface $parent = null);

    public function selector($class, array $query = []);

    public function source($class);

    public function saver($class);
}