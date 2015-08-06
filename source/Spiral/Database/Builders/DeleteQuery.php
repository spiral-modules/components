<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
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
    public function table($into)
    {
        $this->table = $into;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler;

        return $this->flattenParameters($compiler->prepareParameters(
            QueryCompiler::DELETE_QUERY, $this->whereParameters
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler->reset();

        return $compiler->delete($this->table, $this->whereTokens);
    }
}