<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Schemas\IndexInterface;

abstract class AbstractIndex implements IndexInterface
{
    /**
     * Index types.
     */
    const NORMAL = 'INDEX';
    const UNIQUE = 'UNIQUE';










    /**
     * @invisible
     * @var AbstractTable
     */
    protected $table = null;
}