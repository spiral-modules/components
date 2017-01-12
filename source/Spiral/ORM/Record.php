<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Models\ActiveEntityInterface;

abstract class Record extends RecordEntity implements ActiveEntityInterface
{
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