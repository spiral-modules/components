<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Injections;

use Spiral\Database\Entities\QueryCompiler;

/**
 * Declares ability to be converted to sql statement, using (ot not using) query compiler. This
 * interface generally implemented to represent custom piece of SQL code or nested query.
 */
interface SQLFragmentInterface
{
    /**
     * @param QueryCompiler $compiler
     * @return string
     */
    public function sqlStatement(QueryCompiler $compiler = null);

    /**
     * @return string
     */
    public function __toString();
}