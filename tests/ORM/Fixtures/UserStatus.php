<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Columns\EnumColumn;

class UserStatus extends EnumColumn
{
    const VALUES  = ['active', 'disabled'];

    const DEFAULT = 'active';
}