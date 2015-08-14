<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM;

use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\Validation\ValidatesInterface;

/**
 * Relations used to represent data related to parent record. Every relation must be embedded into
 * record, be callable and provide related data by record request. In addition, relations must know
 * how to associate data/entity provided by user.
 *
 * @see Record
 */
interface RelationInterface extends ValidatesInterface
{
    /**
     * @param ORM   $orm        ORM component.
     * @param Record $parent     Parent Record.
     * @param array $definition Relation definition, crated by RelationSchema.
     * @param mixed $data       Pre-loaded relation data.
     * @param bool  $loaded     Indication that relation data has been loaded from database.
     */
    public function __construct(
        ORM $orm,
        Record $parent,
        array $definition,
        $data = null,
        $loaded = false
    );

    /**
     * Check if relation data was loaded (even if no data presented).
     *
     * @return bool
     */
    public function isLoaded();

    /**
     * Return data, object or instances handled by relation, resulted type depends of relation
     * implementation and might be: Record, RecordIterator, itself (ManyToMorphed), Document and etc.
     * Related data must be loaded if relation was not pre-loaded with record.
     *
     * Example:
     * echo $user->profile->facebookUID;
     *
     * @see Record::__get()
     * @return mixed|object
     * @throws RelationException
     */
    public function getRelated();

    /**
     * Associate relation to new object data. Method will be called by parent record when field
     * with name = relation name set with some value. Relation must update inner and outer keys
     * in parent and related records.
     *
     * Example:
     * $user->profile = new Profile();
     *
     * You should be able to disassociate some relations by providing null as value, but only if
     * relation was configured as nullable.
     *
     * Example:
     * $post->picture = null;
     *
     * @see Record::__set()
     * @param mixed|object|null $related
     * @throws RelationException
     * @throws ORMException
     */
    public function associate($related);

    /**
     * Must save related data into database by Record request.
     *
     * @see Record::save()
     * @param bool $validate
     * @return bool
     * @throws RelationException
     */
    public function saveAssociation($validate = true);

    /**
     * Invoke relation with custom arguments. Result may vary based on relation logic.
     *
     * Example:
     * $user->posts(['active' => true])->count()
     *
     * @see Record::__call()
     * @param array $arguments
     * @return mixed
     * @throws RelationException
     */
    public function __invoke(array $arguments);

    /**
     * Reset relation state. By default it must flush all relation data. Method used by Record when
     * context were changed.
     *
     * @see Record::setContext()
     * @param array $data   Set relation data in array form.
     * @param bool  $loaded Indication that relation data has been loaded.
     * @throws RelationException
     */
    public function reset(array $data = [], $loaded = false);
}