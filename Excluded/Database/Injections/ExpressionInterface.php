<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Injections;

use Spiral\Database\Entities\QueryCompiler;

/**
 * Expressions require instance of QueryCompiler at moment of statementGeneration. For
 * simplification purposes every expression is instance of fragment (no compiler is required),
 * however such instance has to be provided at moment of compilation.
 */
interface ExpressionInterface extends FragmentInterface
{
    /**
     * @todo think about alternative implementation
     *
     * @param QueryCompiler|null $compiler
     *
     * @return mixed
     */
    public function sqlStatement(QueryCompiler $compiler = null);
}
