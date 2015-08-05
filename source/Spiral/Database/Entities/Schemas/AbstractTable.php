<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Schemas\TableInterface;

abstract class AbstractTable implements TableInterface
{
    /**
     * {@inheritdoc}
     */
    public function exists()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKeys()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn($name)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractColumn[]
     */
    public function getColumns()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(array $columns = [])
    {
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractIndex[]
     */
    public function getIndexes()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeign($column)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractReference[]
     */
    public function getForeigns()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
    }
}