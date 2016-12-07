<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractElement;

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
     *
     * @param bool $quoted Quote name.
     */
    public function getName(bool $quoted = false): string
    {
        if (empty(parent::getName())) {
            $this->setName($this->generateName());
        }

        return parent::getName($quoted);
    }

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
     * Change index type and behaviour to unique/non-unique state.
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
     * @param bool $includeTable Include table ON statement (not required for inline index
     *                           creation).
     *
     * @return string
     */
    public function sqlStatement(bool $includeTable = true): string
    {
        $statement = [$this->type];

        if ($this->isUnique()) {
            //UNIQUE INDEX
            $statement[] = 'INDEX';
        }

        $statement[] = $this->getName(true);

        if ($includeTable) {
            $statement[] = "ON {$this->table->getName(true)}";
        }

        //Wrapping column names
        $columns = implode(', ', array_map(
            [$this->table->getDriver(), 'identifier']
            , $this->columns
        ));

        $statement[] = "({$columns})";

        return implode(' ', $statement);
    }

    /**
     * Compare two elements together.
     *
     * @param self $initial
     *
     * @return bool
     */
    public function compare(self $initial): bool
    {
        $normalized = clone $initial;
        $normalized->declared = $this->declared;

        return $this == $normalized;
    }

    /**
     * Generate unique index name.
     *
     * @return string
     */
    protected function generateName(): string
    {
        //We can generate name
        $name = $this->table->getName() . '_index_' . implode('_', $this->columns) . '_' . uniqid();

        if (strlen($name) > 64) {
            //Many dbs has limitations on identifier length
            $name = md5($name);
        }

        return $name;
    }
}
