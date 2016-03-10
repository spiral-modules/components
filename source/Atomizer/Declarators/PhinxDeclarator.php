<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Atomizer\Declarators;

use Spiral\Atomizer\DeclaratorInterface;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Reactor\Body\Source;

/**
 * @todo Complete declarator
 */
abstract class PhinxDeclarator implements DeclaratorInterface
{
    /**
     * {@inheritdoc}
     */
    public function dropTable(Source $source, AbstractTable $table)
    {
        $source->addLine("\$this->dropTable('{$table->getName()}');");
    }
}