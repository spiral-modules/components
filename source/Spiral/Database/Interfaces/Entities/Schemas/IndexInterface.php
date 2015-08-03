<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Interfaces\Entities\Schemas;

interface IndexInterface
{
    /**
     * Index name. Name can be changed by calling name($name) method, by default all indexes will
     * get automatically generated identifier including table name and index columns.
     *
     * @param bool $quoted If true index name will be quoted accordingly to driver rules.
     * @return string
     */
    public function getName();

    /**
     * Check if index is unique.
     *
     * @return bool
     */
    public function isUnique();

    /**
     * Column names used to form index.
     *
     * @@return array
     */
    public function getColumns();
}