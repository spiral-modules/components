<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Schemas\Prototypes;

use Interop\Container\ContainerInterface;
use Spiral\Core\Component;
use Spiral\Database\Entities\Driver;
use Spiral\Database\Schemas\TableInterface;

/**
 * AbstractTable class used to describe and manage state of specified table. It provides ability to
 * get table introspection, update table schema and automatically generate set of diff operations.
 *
 * Most of table operation like column, index or foreign key creation/altering will be applied when
 * save() method will be called.
 *
 * Column configuration shortcuts:
 *
 * @method AbstractColumn primary($column)
 * @method AbstractColumn bigPrimary($column)
 * @method AbstractColumn enum($column, array $values)
 * @method AbstractColumn string($column, $length = 255)
 * @method AbstractColumn decimal($column, $precision, $scale)
 * @method AbstractColumn boolean($column)
 * @method AbstractColumn integer($column)
 * @method AbstractColumn tinyInteger($column)
 * @method AbstractColumn bigInteger($column)
 * @method AbstractColumn text($column)
 * @method AbstractColumn tinyText($column)
 * @method AbstractColumn longText($column)
 * @method AbstractColumn double($column)
 * @method AbstractColumn float($column)
 * @method AbstractColumn datetime($column)
 * @method AbstractColumn date($column)
 * @method AbstractColumn time($column)
 * @method AbstractColumn timestamp($column)
 * @method AbstractColumn binary($column)
 * @method AbstractColumn tinyBinary($column)
 * @method AbstractColumn longBinary($column)
 */
abstract class AbstractTable extends Component implements TableInterface
{
    /**
     * @invisible
     *
     * @var Driver
     */
    protected $driver = null;

    /**
     * @return ContainerInterface
     */
    protected function iocContainer()
    {
        //Falling back to driver specific container
        return $this->driver->iocContainer();
    }
}