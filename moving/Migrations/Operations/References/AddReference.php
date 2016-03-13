<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Migrations\Operations\References;

use Spiral\Database\Entities\Schemas\AbstractReference;
use Spiral\Migrations\ContextInterface;
use Spiral\Migrations\Exceptions\Operations\ReferenceException;
use Spiral\Migrations\Operations\ReferenceOperation;
use Spiral\Migrations\Operations\Traits\OptionsTrait;

class AddReference extends ReferenceOperation
{
    use OptionsTrait;

    /**
     * Some options has set of aliases.
     *
     * @var array
     */
    private $aliases = [
        'onDelete' => ['delete'],
        'onUpdate' => ['update']
    ];

    /**
     * @var string
     */
    protected $foreignTable = '';

    /**
     * @var string
     */
    protected $foreignKey = '';

    /**
     * AddReference constructor.
     *
     * @param string $database
     * @param string $table
     * @param string $column
     * @param string $foreignTable
     * @param string $foreignKey
     * @param array  $options
     */
    public function __construct(
        $database,
        $table,
        $column,
        $foreignTable,
        $foreignKey,
        array $options
    ) {
        parent::__construct($database, $table, $column);
        $this->foreignTable = $foreignTable;
        $this->foreignKey = $foreignKey;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(ContextInterface $context)
    {
        $schema = $context->getSchema($this->getDatabase(), $this->getTable());

        if ($schema->hasForeign($this->column)) {
            throw new ReferenceException(
                "Unable to add foreign key '{$schema->getName()}'.({$this->column}), "
                . "foreign key already exists"
            );
        }

        $outerSchema = $context->getSchema($this->database, $this->foreignTable);

        if ($this->foreignTable != $this->table && !$outerSchema->exists()) {
            throw new ReferenceException(
                "Unable to add foreign key '{$schema->getName()}'.'{$this->column}', "
                . "foreign table '{$this->foreignTable}' does not exists"
            );
        }

        if ($this->foreignTable != $this->table && !$outerSchema->hasColumn($this->foreignKey)) {
            throw new ReferenceException(
                "Unable to add foreign key '{$schema->getName()}'.'{$this->column}',"
                . " foreign column '{$this->foreignTable}'.'{$this->foreignKey}' does not exists"
            );
        }

        $foreignKey = $schema->foreign($this->column)->references(
            $this->foreignTable,
            $this->foreignKey
        );

        /*
         * We are allowing both formats "NO_ACTION" and "NO ACTION".
         */

        $foreignKey->onDelete(
            str_replace('_', ' ', $this->getOption('delete', AbstractReference::NO_ACTION))
        );

        $foreignKey->onUpdate(
            str_replace('_', ' ', $this->getOption('update', AbstractReference::NO_ACTION))
        );
    }
}