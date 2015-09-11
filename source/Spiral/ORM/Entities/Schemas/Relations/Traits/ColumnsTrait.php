<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */

namespace Spiral\ORM\Entities\Schemas\Relations\Traits;

use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\ORM\Exceptions\DefinitionException;

/**
 * Trait provides ability for relation to cast columns in specified table using format identical to
 * format used in record schema. Used by relations with pivot tables.
 */
trait ColumnsTrait
{
    /**
     * Cast (specify) column schema based on provided column definition. Column definition are
     * compatible with database Migrations, AbstractColumn types and Record schema.
     *
     * @param AbstractColumn $column
     * @param string         $definition
     * @return AbstractColumn
     * @throws DefinitionException
     * @throws \Spiral\Database\Exceptions\SchemaException
     */
    protected function castColumn(AbstractColumn $column, $definition)
    {
        //Expression used to declare column type, easy to read
        $pattern = '/(?P<type>[a-z]+)(?: *\((?P<options>[^\)]+)\))?(?: *, *(?P<nullable>null(?:able)?))?/i';

        if (!preg_match($pattern, $definition, $type)) {
            throw new DefinitionException(
                "Invalid column type definition in '{$this}'.'{$column->getName()}'."
            );
        }

        if (!empty($type['options'])) {
            //Exporting and trimming
            $type['options'] = array_map('trim', explode(',', $type['options']));
        }

        //We are forcing every column to be NOT NULL by default, DEFAULT value should fix potential
        //problems, nullable flag must be applied before type was set (some types do not want
        //null values to be allowed)
        $column->nullable(!empty($type['nullable']));

        //Bypassing call to AbstractColumn->__call method (or specialized column method)
        call_user_func_array(
            [$column, $type['type']],
            !empty($type['options']) ? $type['options'] : []
        );

        /**
         * No default value is casted for columns created by relations.
         */

        return $column;
    }
}