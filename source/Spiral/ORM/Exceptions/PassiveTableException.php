<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Exceptions;

use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\ORM\Entities\Schemas\RecordSchema;

/**
 * Raised when user or relation edits/creates columns in table associated to record with
 * ACTIVE_SCHEMA constant set to false. Tables like that counted as passive and their schema must
 * not be altered by ORM schema synchronizer.
 */
class PassiveTableException extends SchemaException
{
    /**
     * @param AbstractTable $table
     * @param RecordSchema  $record
     */
    public function __construct(AbstractTable $table, RecordSchema $record)
    {
        $altered = [];
        foreach ($table->alteredColumns() as $column) {
            $altered[] = $column->getName();
        }

        parent::__construct(\Spiral\interpolate(
            'Passive table "{database}"."{table}" ({record}), were altered, columns: {columns}',
            [
                'database' => $record->getDatabase(),
                'table'    => $table->getName(),
                'record'   => $record,
                'columns'  => join(', ', $altered)
            ]
        ));
    }
}