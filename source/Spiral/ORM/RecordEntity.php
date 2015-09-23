<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */

namespace Spiral\ORM;

use Spiral\Models\SchematicEntity;

/**
 * Basic instance of ORM record without active record stuff. Responsible for data hydration and
 * relations.
 */
class RecordEntity extends SchematicEntity implements RecordInterface
{

}