<?php
/**
 * components
 *
 * @author    Wolfy-J
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
    public function getName(): string
    {
        if (empty(parent::getName())) {
            //Let's generate index name on a fly
            $this->setName($this->generateName());
        }

        return parent::getName();
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

    //--MODIFICATIONS

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

        $statement[] = $driver->identifier($this->getName());

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

    /**
     * Generate unique index name.
     *
     * @return string
     */
    protected function generateName(): string
    {
        //We can generate name
        $name = $this->table . '_index_' . implode('_', $this->columns) . '_' . uniqid();

        if (strlen($name) > 64) {
            //Many dbs has limitations on identifier length
            $name = md5($name);
        }

        return $name;
    }
}