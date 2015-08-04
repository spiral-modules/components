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
 * Represents single foreign key and it's options.
 */
interface ReferenceInterface
{
    /**
     * Default delete and update foreign key rules.
     */
    const CASCADE   = 'CASCADE';
    const NO_ACTION = 'NO ACTION';

    /**
     * Constraint name
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
     * Get delete rule, possible values: NO ACTION, CASCADE and etc.
     *
     * @return string
     */
    public function getDeleteRule();

    /**
     * Get update rule, possible values: NO ACTION, CASCADE and etc.
     *
     * @return string
     */
    public function getUpdateRule();
}