<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\ActiveEntityInterface;
use Spiral\Models\SchematicEntity;
use Spiral\Models\Traits\SolidableTrait;

abstract class Record extends SchematicEntity implements ActiveEntityInterface
{
    use SaturateTrait, SolidableTrait;

    const SCHEMA = [];

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