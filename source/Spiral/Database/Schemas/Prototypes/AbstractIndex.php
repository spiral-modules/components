<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Database\Schemas\Prototypes;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Schemas\IndexInterface;

/**
 * Abstract index schema with read (see IndexInterface) and write abilities. Must be implemented
 * by driver to support DBMS specific syntax and creation rules.
 */
abstract class AbstractIndex extends AbstractElement implements IndexInterface
{
    /**
     * Index types.
     */
    const NORMAL = 'INDEX';
    const UNIQUE = 'UNIQUE';

    /**
     * Index type, by default NORMAL and UNIQUE indexes supported, additional types can be
     * implemented on database driver level.
     *
     * @var string
     */
    protected $type = self::NORMAL;

    /**
     * Columns used to form index.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * {@inheritdoc}
     */
    public function isUnique(): bool
    {
        return $this->type == self::UNIQUE;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Declare index type and behaviour to unique/non-unique state.
     *
     * @param bool $unique
     *
     * @return self
     */
    public function unique(bool $unique = true): AbstractIndex
    {
        $this->type = $unique ? self::UNIQUE : self::NORMAL;

        return $this;
    }

    /**
     * Change set of index forming columns. Method must support both array and string parameters.
     *
     * Example:
     * $index->columns('key');
     * $index->columns('key', 'key2');
     * $index->columns(['key', 'key2']);
     *
     * @param string|array $columns Columns array or comma separated list of parameters.
     *
     * @return self
     */
    public function columns($columns): AbstractIndex
    {
        if (!is_array($columns)) {
            $columns = func_get_args();
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * Index sql creation syntax.
     *
     * @param Driver $driver
     * @param bool   $includeTable Include table ON statement (not required for inline index
     *                             creation).
     *
     * @return string
     */
    public function sqlStatement(Driver $driver, bool $includeTable = true): string
    {
        $statement = [$this->type == self::UNIQUE ? 'UNIQUE INDEX' : 'INDEX'];

        $statement[] = $driver->identifier($this->name);

        if ($includeTable) {
            $statement[] = "ON {$driver->identifier($this->table)}";
        }

        //Wrapping column names
        $columns = implode(', ', array_map([$driver, 'identifier'], $this->columns));

        $statement[] = "({$columns})";

        return implode(' ', $statement);
    }

    /**
     * {@inheritdoc}
     */
    public function compare(IndexInterface $initial): bool
    {
        return $this == clone $initial;
    }
}