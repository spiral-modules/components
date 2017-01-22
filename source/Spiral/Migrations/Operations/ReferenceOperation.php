<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Migrations\Operations;

abstract class ReferenceOperation extends TableOperation
{
    /**
     * Some options has set of aliases.
     *
     * @var array
     */
    protected $aliases = [
        'onDelete' => ['delete'],
        'onUpdate' => ['update']
    ];

    /**
     * Column foreign key associated to.
     *
     * @var string
     */
    protected $column = '';

    /**
     * @param string|null $database
     * @param string      $table
     * @param string      $column
     */
    public function __construct($database, string $table, string $column)
    {
        parent::__construct($database, $table);

        $this->column = $column;
    }
}