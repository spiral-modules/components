<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Migrations\Operations\Table;

use Spiral\Migrations\ContextInterface;
use Spiral\Migrations\Exceptions\Operations\TableException;
use Spiral\Migrations\Operations\TableOperation;

class UpdateTable extends TableOperation
{
    /**
     * {@inheritdoc}
     */
    public function execute(ContextInterface $context)
    {
        $schema = $context->getSchema($this->getDatabase(), $this->getTable());
        $database = !empty($this->database) ? $this->database : '[default]';

        if (!$schema->exists()) {
            throw new TableException(
                "Unable to update table '{$database}'.'{$this->getTable()}', no table exists"
            );
        }

        $schema->save(true, true, true);
    }
}