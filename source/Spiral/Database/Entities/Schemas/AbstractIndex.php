<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Schemas\IndexInterface;

/**
 *      * Instance on IndexSchema represent one table index - name, type and involved columns. Attention,
 * based on index mapping and resolving (based on set of column name), there is no simple way to
 * create multiple indexes with same set of columns, as they will be resolved as one index.
 */
abstract class AbstractIndex implements IndexInterface
{
    /**
     * Index types.
     */
    const NORMAL = 'INDEX';
    const UNIQUE = 'UNIQUE';

    /**
     * Index name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Index type, by default NORMAL and UNIQUE indexes supported, additional types can be implemented
     * on database driver level.
     *
     * @var int
     */
    protected $type = self::NORMAL;

    /**
     * Columns used to form index.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * @invisible
     * @var AbstractTable
     */
    protected $table = null;

    /**
     * @param AbstractTable $table
     * @param string        $name
     * @param mixed         $schema Driver specific index information.
     */
    public function __construct(AbstractTable $table, $name, $schema = null)
    {
        $this->name = $name;
        $this->table = $table;

        $schema && $this->resolveSchema($schema);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $quoted Quote name.
     */
    public function getName($quoted = false)
    {
        $name = $this->name;
        if (empty($this->name))
        {
            $name = $this->table->getName() . '_index_' . join('_', $this->columns) . '_' . uniqid();
        }

        if (strlen($name) > 64)
        {
            //Many dbs has limitations on identifier length
            $name = md5($name);
        }

        return $quoted ? $this->table->driver()->identifier($name) : $name;
    }

    /**
     * {@inheritdoc}
     */
    public function isUnique()
    {
        return $this->type == self::UNIQUE;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns()
    {
        return $this->columns;
    }
}