<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM;

use Spiral\Database\Exceptions\QueryException;
use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\Events\EntityEvent;
use Spiral\ORM\Entities\RecordSource;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\Exceptions\RecordException;
use Spiral\ORM\Traits\FindTrait;

/**
 * Entity with ability to be saved and direct access to source.
 *
 * @todo potentially dedicate save operation to SourceEntirelly, OR expose compileUpdates method
 */
class Record extends RecordEntity implements ActiveEntityInterface
{
    /**
     * Static find methods.
     */
    use FindTrait;

    /**
     * Indication that save methods must be validated by default, can be altered by calling save
     * method with user arguments.
     */
    const VALIDATE_SAVE = true;

    /**
     * {@inheritdoc}
     *
     * Create or update record data in database. Record will validate all EMBEDDED and loaded
     * relations.
     *
     * @see   sourceTable()
     * @see   updateChriteria()
     * @param bool|null $validate  Overwrite default option declared in VALIDATE_SAVE to force or
     *                             disable validation before saving.
     * @return bool
     * @throws RecordException
     * @throws QueryException
     * @event saving()
     * @event saved()
     * @event updating()
     * @event updated()
     */
    public function save($validate = null)
    {
        if (is_null($validate)) {
            //Using default model behaviour
            $validate = static::VALIDATE_SAVE;
        }

        if ($validate && !$this->isValid()) {
            return false;
        }

        if (!$this->isLoaded()) {
            $this->dispatch('saving', new EntityEvent($this));

            //Primary key field name (if any)
            $primaryKey = $this->ormSchema()[ORM::M_PRIMARY_KEY];

            //We will need to support records with multiple primary keys in future
            unset($this->fields[$primaryKey]);

            //Creating
            $lastID = $this->sourceTable()->insert($this->fields = $this->serializeData());
            if (!empty($primaryKey)) {
                //Updating record primary key
                $this->fields[$primaryKey] = $lastID;
            }

            $this->loadedState(true)->dispatch('saved', new EntityEvent($this));
        } elseif ($this->isSolid() || $this->hasUpdates()) {
            $this->dispatch('updating', new EntityEvent($this));

            //Updating changed/all field based on model criteria (in usual case primaryKey)
            $this->sourceTable()->update(
                $this->compileUpdates(),
                $this->stateCriteria()
            )->run();

            $this->dispatch('updated', new EntityEvent($this));
        }

        $this->flushUpdates();
        $this->saveRelations($validate);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @event deleting()
     * @event deleted()
     */
    public function delete()
    {
        $this->dispatch('deleting', new EntityEvent($this));

        if ($this->isLoaded()) {
            $this->sourceTable()->delete($this->stateCriteria())->run();
        }

        //We don't really need to delete embedded or loaded relations,
        //we have foreign keys for that

        $this->fields = $this->ormSchema()[ORM::M_COLUMNS];
        $this->loadedState(self::DELETED)->dispatch('deleted', new EntityEvent($this));
    }

    /**
     * Instance of ORM Selector associated with specific document.
     *
     * @see   Component::staticContainer()
     * @param ORM $orm ORM component, global container will be called if not instance provided.
     * @return RecordSource
     * @throws ORMException
     */
    public static function source(ORM $orm = null)
    {
        /**
         * Using global container as fallback.
         *
         * @var ORM $orm
         */
        if (empty($orm)) {
            //Using global container as fallback
            $orm = self::staticContainer()->get(ORM::class);
        }

        return $orm->source(static::class);
    }

    /**
     * Save embedded relations.
     *
     * @param bool $validate
     */
    private function saveRelations($validate)
    {
        foreach ($this->relations as $name => $relation) {
            if (!$relation instanceof RelationInterface) {
                //Was never constructed
                continue;
            }

            if ($this->isEmbedded($name) && !$relation->saveAssociation($validate)) {
                throw new RecordException("Unable to save relation '{$name}'.");
            }
        }
    }
}
