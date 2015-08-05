<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Schemas\ColumnInterface;

abstract class AbstractColumn implements ColumnInterface
{
    /**
     * @invisible
     * @var AbstractTable
     */
    protected $table = null;

    /**
     * {@inheritdoc}
     *
     * @param bool $quoted Quote name.
     */
    public function getName($quoted = false)
    {
        return $quoted ? $this->table->driver()->identifier($this->name) : $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function phpType()
    {
        $schemaType = $this->abstractType();
        foreach ($this->phpMapping as $phpType => $candidates) {
            if (in_array($schemaType, $candidates)) {
                return $phpType;
            }
        }

        return 'string';
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * {@inheritdoc}
     */
    public function getScale()
    {
        return $this->scale;
    }

    /**
     * {@inheritdoc}
     */
    public function isNullable()
    {
        return $this->nullable;
    }

    /**
     * {@inheritdoc}
     */
    public function hasDefaultValue()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValue()
    {
    }

    /**
     * Get abstract type name, this method will map one of database types to limited set of ColumnSchema
     * abstract types.
     *
     * Attention, this method is not used for schema comparasions (database type used), it's only for
     * decorative purposes. If schema can't resolve type - "unknown" will be returned (by default
     * mapped to php type string).
     *
     * @return string
     */
    public function abstractType()
    {
        foreach ($this->reverseMapping as $type => $candidates) {
            foreach ($candidates as $candidate) {
                if (is_string($candidate)) {
                    if (strtolower($candidate) == strtolower($this->type)) {
                        return $type;
                    }

                    continue;
                }

                if (strtolower($candidate['type']) != strtolower($this->type)) {
                    continue;
                }

                foreach ($candidate as $option => $required) {
                    if ($option == 'type') {
                        continue;
                    }

                    if ($this->$option != $required) {
                        continue 2;
                    }
                }

                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Give new name to column. Do not use this method to rename existed columns, use
     * TableSchema->renameColumn(). This is internal method used to rename column inside schema.
     *
     * @param string $name New column name.
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Compile column create statement.
     *
     * @return string
     */
    public function sqlStatement()
    {
        $statement = [$this->getName(true), $this->type];

        if ($this->abstractType() == 'enum') {
            if ($enumDefinition = $this->enumType()) {
                $statement[] = $enumDefinition;
            }
        } elseif (!empty($this->precision)) {
            $statement[] = "({$this->precision}, {$this->scale})";
        } elseif (!empty($this->size)) {
            $statement[] = "({$this->size})";
        }

        $statement[] = $this->nullable ? 'NULL' : 'NOT NULL';

        if ($this->defaultValue !== null) {
            $statement[] = "DEFAULT {$this->prepareDefault()}";
        }

        return join(' ', $statement);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->sqlStatement();
    }

    /**
     * Simplified way to dump information.
     *
     * @return object
     */
    public function __debugInfo()
    {
        $column = [
            'name' => $this->name,
            'type' => [
                'database' => $this->type,
                'schema'   => $this->abstractType(),
                'php'      => $this->phpType()
            ]
        ];

        if (!empty($this->size)) {
            $column['size'] = $this->size;
        }

        if ($this->nullable) {
            $column['nullable'] = true;
        }

        if ($this->defaultValue !== null) {
            $column['defaultValue'] = $this->getDefaultValue();
        }

        if ($this->abstractType() == 'enum') {
            $column['enumValues'] = $this->enumValues;
        }

        if ($this->abstractType() == 'decimal') {
            $column['precision'] = $this->precision;
            $column['scale'] = $this->scale;
        }

        return (object)$column;
    }

    /**
     * Parse column information provided by parent TableSchema and populate column values.
     *
     * @param mixed $schema Column information fetched from database by TableSchema. Format depends
     *                      on driver type.
     * @return mixed
     */
    abstract protected function resolveSchema($schema);
}