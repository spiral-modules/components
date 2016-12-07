<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Drivers\SQLite\Schemas;

use Spiral\Database\Schemas\AbstractColumn;
use Spiral\Database\Schemas\AbstractCommander;
use Spiral\Database\Schemas\AbstractReference;
use Spiral\Database\Schemas\AbstractTable;

/**
 * SQLite commander.
 */
class Commander extends AbstractCommander
{
    /**
     * {@inheritdoc}
     */
    public function addColumn(AbstractTable $table, AbstractColumn $column)
    {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    public function dropColumn(AbstractTable $table, AbstractColumn $column)
    {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    public function alterColumn(
        AbstractTable $table,
        AbstractColumn $initial,
        AbstractColumn $column
    ) {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    public function addForeign(AbstractTable $table, AbstractReference $foreign)
    {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeign(AbstractTable $table, AbstractReference $foreign)
    {
        //Not supported
    }

    /**
     * {@inheritdoc}
     */
    public function alterForeign(
        AbstractTable $table,
        AbstractReference $initial,
        AbstractReference $foreign
    ) {
        //Not supported
    }
}
