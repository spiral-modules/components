<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Schemas;

/**
 * Represent single table index associated with set of columns.
 */
interface IndexInterface
{
    /**
     * Index name.
     *
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
     * @return array
     */
    public function getColumns();
}