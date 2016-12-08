<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Schemas\Prototypes;

use Spiral\Database\Schemas\ColumnInterface;

/**
 * Abstract column schema with read (see ColumnInterface) and write abilities. Must be implemented
 * by driver to support DBMS specific syntax and creation rules.
 *
 * Shortcuts for various column types:
 *
 * @method AbstractColumn|$this boolean()
 * @method AbstractColumn|$this integer()
 * @method AbstractColumn|$this tinyInteger()
 * @method AbstractColumn|$this bigInteger()
 * @method AbstractColumn|$this text()
 * @method AbstractColumn|$this tinyText()
 * @method AbstractColumn|$this longText()
 * @method AbstractColumn|$this double()
 * @method AbstractColumn|$this float()
 * @method AbstractColumn|$this datetime()
 * @method AbstractColumn|$this date()
 * @method AbstractColumn|$this time()
 * @method AbstractColumn|$this timestamp()
 * @method AbstractColumn|$this binary()
 * @method AbstractColumn|$this tinyBinary()
 * @method AbstractColumn|$this longBinary()
 * @method AbstractColumn|$this json()
 */
abstract class AbstractColumn implements ColumnInterface
{

}