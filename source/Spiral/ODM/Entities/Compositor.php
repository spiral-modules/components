<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Entities;

use Spiral\Core\Component;
use Spiral\ODM\CompositableInterface;
use Spiral\Validation\ValidatesInterface;

class Compositor extends Component implements CompositableInterface,
    \IteratorAggregate,
    \Countable,
    \ArrayAccess, ValidatesInterface
{

}