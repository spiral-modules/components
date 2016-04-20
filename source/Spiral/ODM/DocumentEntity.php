<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM;

use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\Events\EntityEvent;
use Spiral\Models\SchematicEntity;
use Spiral\ODM\Exceptions\DefinitionException;

/**
 * Primary class for spiral ODM, provides ability to pack it's own updates in a form of atomic
 * updates.
 */
class DocumentEntity extends SchematicEntity
{
    use SaturateTrait;

    /**
     * We are going to inherit parent validation rules, this will let spiral translator know about
     * it and merge i18n messages.
     *
     * @see TranslatorTrait
     */
    const I18N_INHERIT_MESSAGES = true;

    /**
     * Create document entity using given ODM instance or load parent ODM via shared container.
     *
     * @see   Component::staticContainer()
     *
     * @param array        $fields Model fields to set, will be passed thought filters.
     * @param ODMInterface $odm    ODMInterface component, global container will be called if not
     *                             instance provided.
     *
     * @return DocumentEntity
     *
     * @event created($document)
     */
    public static function create($fields = [], ODMInterface $odm = null)
    {
        /**
         * @var DocumentEntity $document
         */
        $document = new static([], null, $odm);

        //Forcing validation (empty set of fields is not valid set of fields)
        $document->setFields($fields)->dispatch('created', new EntityEvent($document));

        return $document;
    }

    /**
     * Called by ODM with set of loaded fields. Must return name of appropriate class.
     *
     * @param array        $fields
     * @param ODMInterface $odm
     *
     * @return string
     *
     * @throws DefinitionException
     */
    public static function defineClass(array $fields, ODMInterface $odm)
    {
        throw new DefinitionException('Class definition method has not been implemented');
    }
}