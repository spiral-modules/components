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
use Spiral\Validation\ValidatesInterface;

/**
 * Relations used to represent data related to parent model. Every relation must be embedded into
 * model, be callable and provide related data by model request. In addition, relations must know
 * how to associate data/entity provided by user.
 *
 * @see Model
 */
interface RelationInterface extends ValidatesInterface
{
    /**
     * @param ORM   $orm        ORM component.
     * @param Model $parent     Parent Model.
     * @param array $definition Relation definition, crated by RelationSchema.
     * @param mixed $data       Pre-loaded relation data.
     * @param bool  $loaded     Indication that relation data has been loaded from database.
     */
    public function __construct(
        ORM $orm,
        Model $parent,
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
     * implementation and might be: Model, ModelIterator, itself (ManyToMorphed), Document and etc.
     * Related data must be loaded if relation was not pre-loaded with model.
     *
     * Example:
     * echo $user->profile->facebookUID;
     *
     * @see Model::__get()
     * @return mixed|object
     */
    public function getRelated();

    /**
     * Associate relation to new object data. Method will be called by parent model when field
     * with name = relation name set with some value. Relation must update inner and outer keys
     * in parent and related models.
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
     * @see Model::__set()
     * @param mixed|object|null $related
     * @throws ORMException
     */
    public function associate($related);

    /**
     * Must save related data into database by Model request.
     *
     * @see Model::save()
     * @param bool $validate
     * @return bool
     */
    public function saveAssociation($validate = true);

    /**
     * Invoke relation with custom arguments. Result may vary based on relation logic.
     *
     * Example:
     * $user->posts(['active' => true])->count()
     *
     * @see Model::__call()
     * @param array $arguments
     * @return mixed
     */
    public function __invoke(array $arguments);

    /**
     * Reset relation state. By default it must flush all relation data. Method used by Model when
     * context were changed.
     *
     * @see Model::setContext()
     * @param array $data   Set relation data in array form.
     * @param bool  $loaded Indication that relation data has been loaded.
     */
    public function reset(array $data = [], $loaded = false);
}