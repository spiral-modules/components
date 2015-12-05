<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Builders;

use Spiral\Database\Builders\Prototypes\AbstractAffect;
use Spiral\Database\Entities\QueryCompiler;

/**
 * Update statement builder.
 */
class DeleteQuery extends AbstractAffect
{
    /**
     * Change target table.
     *
     * @param string $into Table name without prefix.
     * @return $this
     */
    public function from($into)
    {
        $this->table = $into;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(QueryCompiler $compiler = null)
    {
        if (empty($compiler)) {
            $compiler = $this->compiler;
        }

        return $this->flattenParameters($compiler->orderParameters(
            QueryCompiler::DELETE_QUERY, $this->whereParameters
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        if (empty($compiler)) {
            $compiler = $this->compiler->resetQuoter();
        }

        return $compiler->compileDelete($this->table, $this->whereTokens);
    }
}