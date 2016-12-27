<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Core\Traits\SaturateTrait;
use Spiral\Models\SchematicEntity;
use Spiral\Models\Traits\SolidableTrait;

class Record extends SchematicEntity
{
    use SaturateTrait, SolidableTrait;
}