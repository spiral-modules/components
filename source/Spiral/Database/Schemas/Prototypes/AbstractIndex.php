<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Schemas\Prototypes;

use Spiral\Database\Schemas\IndexInterface;

/**
 * Abstract index schema with read (see IndexInterface) and write abilities. Must be implemented
 * by driver to support DBMS specific syntax and creation rules.
 */
abstract class AbstractIndex implements IndexInterface
{

}