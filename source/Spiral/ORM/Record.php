<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\Traits\SolidableTrait;

abstract class Record extends RecordEntity implements ActiveEntityInterface
{
    use SaturateTrait, SolidableTrait;

    /**
     * Constants used to declare indexes in record schema.
     *
     * @see Record::$indexes
     */
    const INDEX  = 1000;            //Default index type
    const UNIQUE = 2000;            //Unique index definition

    const SCHEMA   = [];
    const DEFAULTS = [];
    const INDEXES  = [];

    public function isLoaded(): bool
    {
        // TODO: Implement isLoaded() method.
    }

    public function primaryKey()
    {
        // TODO: Implement primaryKey() method.
    }

    public function save(): int
    {
        // TODO: Implement save() method.
    }

    public function delete()
    {
        // TODO: Implement delete() method.
    }
}