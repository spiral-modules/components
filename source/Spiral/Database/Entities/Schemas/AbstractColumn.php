<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Schemas\ColumnInterface;

abstract class AbstractColumn implements ColumnInterface
{
    /**
     * @invisible
     * @var AbstractTable
     */
    protected $table = null;
}