<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Schemas;

interface ReferenceInterface
{
    /**
     * Constraint name. Foreign key name can not be changed manually, while table creation name will
     * be generated automatically.
     *
     * @return string
     */
    public function getName();

    /**
     * Get column name foreign key assigned to.
     *
     * @return string
     */
    public function getColumn();

    /**
     * Foreign table name.
     *
     * @return string
     */
    public function getForeignTable();

    /**
     * Foreign key (column name).
     *
     * @return string
     */
    public function getForeignKey();

    /**
     * Get delete rule, possible values: NO ACTION, CASCADE
     *
     * @return string
     */
    public function getDeleteRule();

    /**
     * Get update rule, possible values: NO ACTION, CASCADE
     *
     * @return string
     */
    public function getUpdateRule();
}