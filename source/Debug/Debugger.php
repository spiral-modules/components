<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug;

use Spiral\Core\Singleton;
use Spiral\Core\Component;

class Debugger extends Singleton
{
    use Component\ConfigurableTrait, Component\LoggerTrait;
}