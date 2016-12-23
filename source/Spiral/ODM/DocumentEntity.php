<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\SchematicEntity;
use Spiral\ODM\Entities\DocumentInstantiator;

abstract class DocumentEntity extends SchematicEntity// implements CompositableInterface
{
    use SaturateTrait;

    /**
     * Set of schema sections needed to describe entity behaviour.
     */
    const SH_INSTANTIATION = 0;
    const SH_DEFAULTS      = 1;
    const SH_AGGREGATIONS  = 6;
    const SH_COMPOSITIONS  = 7;

    /**
     * Class responsible for instance construction.
     */
    const INSTANTIATOR = DocumentInstantiator::class;

    /**
     * Document fields, accessors and relations. ODM will generate setters and getters for some
     * fields based on their types.
     *
     * Example, fields:
     * const SCHEMA = [
     *      '_id'    => 'MongoId', //Primary key field
     *      'value'  => 'string',  //Default string field
     *      'values' => ['string'] //ScalarArray accessor will be applied for fields like that
     * ];
     *
     * Compositions:
     * const SCHEMA = [
     *     ...,
     *     'child'       => Child::class,   //One document are composited, for example user Profile
     *     'many'        => [Child::class]  //Compositor accessor will be applied, allows to
     *                                      //composite many document instances
     * ];
     *
     * Documents can extend each other, in this case schema will also be inherited.
     *
     * @var array
     */
    const SCHEMA = [];

    /**
     * Default field values.
     *
     * @var array
     */
    const DEFAULTS = [];

    /**
     * Model behaviour configurations.
     */
    const HIDDEN    = [];
    const FILLABLE  = [];
    const SETTERS   = [];
    const GETTERS   = [];
    const ACCESSORS = [];

//    /**
//     * {@inheritdoc}
//     *
//     * @param array|null $schema
//     */
//    public function __construct(
//        $fields,
//        ODMInterface $odm = null,
//        $schema = null
//    ) {
//        //We can use global container as fallback if no default values were provided
//        $this->odm = $this->saturate($odm, ODMInterface::class);
//        $this->odmSchema = !empty($schema) ? $schema : $this->odm->schema(static::class);
//
//        $fields = is_array($fields) ? $fields : [];
//        if (!empty($this->odmSchema[ODM::D_DEFAULTS])) {
//            /*
//             * Merging with default values
//             */
//            $fields = array_replace_recursive($this->odmSchema[ODM::D_DEFAULTS], $fields);
//        }
//
//        parent::__construct($fields, $this->odmSchema);
//    }
}