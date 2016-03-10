<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Atomizer\Declarators;

use Spiral\Atomizer\DeclaratorInterface;
use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractIndex;
use Spiral\Database\Entities\Schemas\AbstractReference;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\Database\Entities\Schemas\Comparator;
use Spiral\Reactor\Body\Source;
use Spiral\Support\Serializer;

/**
 * Generates spiral specific migration command sequence.
 */
class SpiralDeclarator implements DeclaratorInterface
{
    /**
     * Comparator alteration states.
     */
    const NEW_STATE      = 0;
    const ORIGINAL_STATE = 1;

    /**
     * @var AliaserInterface|null
     */
    private $aliaser = null;

    /**
     * @var Serializer
     */
    private $serializer = null;

    /**
     * @param AliaserInterface $aliaser
     */
    public function __construct(AliaserInterface $aliaser = null)
    {
        $this->aliaser = $aliaser;
        $this->serializer = new Serializer();
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(Source $source, AbstractTable $table)
    {
        //Header
        $source->addLine("\$this->table({$this->tableAlias($table)})");

        $this->declareColumns($source, $table->comparator());
        $this->declareIndexes($source, $table->comparator());
        $this->declareForeigns($source, $table->comparator(), $table->getPrefix());

        //Finalization
        $source->addLine("    ->create();");
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable(Source $source, AbstractTable $table)
    {
        $source->addLine("\$this->table({$this->tableAlias($table)})");

        $this->declareColumns($source, $table->comparator());
        $this->declareIndexes($source, $table->comparator());
        $this->declareForeigns($source, $table->comparator(), $table->getPrefix());

        //Finalization
        $source->addLine("    ->update();");
    }

    /**
     * {@inheritdoc}
     */
    public function revertTable(Source $source, AbstractTable $table)
    {
        $source->addLine("\$this->table({$this->tableAlias($table)})");

        $this->revertForeigns($source, $table->comparator(), $table->getPrefix());
        $this->revertIndexes($source, $table->comparator());
        $this->revertColumns($source, $table->comparator());

        //Finalization
        $source->addLine("    ->update();");
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable(Source $source, AbstractTable $table)
    {
        $source->addLine("\$this->table({$this->tableAlias($table)})->drop();");
    }

    /**
     * @param Source     $source
     * @param Comparator $comparator
     */
    protected function declareColumns(Source $source, Comparator $comparator)
    {
        foreach ($comparator->addedColumns() as $column) {
            $name = "'{$column->getName()}'";
            $type = "'{$column->abstractType()}'";

            $source->addString("    ->addColumn({$name}, {$type}, {$this->columnOptions($column)})");
        }

        foreach ($comparator->alteredColumns() as $pair) {
            /**
             * @var AbstractColumn $column
             */
            $column = $pair[self::NEW_STATE];

            $name = "'{$column->getName()}'";
            $type = "'{$column->abstractType()}'";
            $source->addString("    ->alterColumn({$name}, {$type}, {$this->columnOptions($column)})");
        }

        foreach ($comparator->droppedColumns() as $column) {
            $source->addLine("    ->dropColumn('{$column->getName()}')");
        }
    }

    /**
     * @param Source     $source
     * @param Comparator $comparator
     */
    protected function declareIndexes(Source $source, Comparator $comparator)
    {
        foreach ($comparator->addedIndexes() as $index) {
            $columns = '[\'' . join('\', \'', $index->getColumns()) . '\']';
            $source->addString("    ->addIndex({$columns}, " . $this->indexOptions($index) . ")");
        }

        foreach ($comparator->alteredIndexes() as $pair) {
            /**
             * @var AbstractIndex $index
             */
            $index = $pair[self::NEW_STATE];

            $columns = '[\'' . join('\', \'', $index->getColumns()) . '\']';
            $source->addString("    ->alterIndex({$columns}, " . $this->indexOptions($index) . ")");
        }

        foreach ($comparator->droppedIndexes() as $index) {
            $columns = '[\'' . join('\', \'', $index->getColumns()) . '\']';
            $source->addString("    ->dropIndex({$columns})");
        }
    }

    /**
     * @param Source     $source
     * @param Comparator $comparator
     * @param string     $prefix Database isolation prefix
     */
    protected function declareForeigns(Source $source, Comparator $comparator, $prefix = '')
    {
        foreach ($comparator->addedForeigns() as $foreign) {
            $column = "'{$foreign->getColumn()}'";
            $table = "'" . substr($foreign->getForeignTable(), strlen($prefix)) . "'";
            $key = "'{$foreign->getForeignKey()}'";

            $source->addString(
                "    ->addForeignKey({$column}, {$table}, {$key}, " . $this->foreignOptions($foreign) . ")"
            );
        }

        foreach ($comparator->alteredForeigns() as $pair) {
            /**
             * @var AbstractReference $foreign
             */
            $foreign = $pair[self::NEW_STATE];

            $column = "'{$foreign->getColumn()}'";
            $table = "'" . substr($foreign->getForeignTable(), strlen($prefix)) . "'";
            $key = "'{$foreign->getForeignKey()}'";

            $source->addString(
                "    ->alterForeignKey({$column}, {$table}, {$key}, " . $this->foreignOptions($foreign) . ")"
            );
        }

        foreach ($comparator->droppedForeigns() as $foreign) {
            $column = "'{$foreign->getColumn()}'";
            $source->addString("    ->dropForeignKey({$column})");
        }
    }

    /**
     * @param Source     $source
     * @param Comparator $comparator
     */
    protected function revertColumns(Source $source, Comparator $comparator)
    {
        foreach ($comparator->droppedColumns() as $column) {
            $name = "'{$column->getName()}'";
            $type = "'{$column->abstractType()}'";

            $source->addString("    ->addColumn({$name}, {$type}, {$this->columnOptions($column)})");
        }

        foreach ($comparator->alteredColumns() as $pair) {
            /**
             * @var AbstractColumn $column
             */
            $column = $pair[self::ORIGINAL_STATE];

            $name = "'{$column->getName()}'";
            $type = "'{$column->abstractType()}'";
            $source->addString("    ->alterColumn({$name}, {$type}, {$this->columnOptions($column)})");
        }

        foreach ($comparator->addedColumns() as $column) {
            $source->addLine("    ->dropColumn('{$column->getName()}')");
        }
    }

    /**
     * @param Source     $source
     * @param Comparator $comparator
     */
    protected function revertIndexes(Source $source, Comparator $comparator)
    {
        foreach ($comparator->droppedIndexes() as $index) {
            $columns = '[\'' . join('\', \'', $index->getColumns()) . '\']';
            $source->addString("    ->addIndex({$columns}, " . $this->indexOptions($index) . ")");
        }

        foreach ($comparator->alteredIndexes() as $pair) {
            /**
             * @var AbstractIndex $index
             */
            $index = $pair[self::ORIGINAL_STATE];

            $columns = '[\'' . join('\', \'', $index->getColumns()) . '\']';
            $source->addString("    ->alterIndex({$columns}, " . $this->indexOptions($index) . ")");
        }

        foreach ($comparator->addedIndexes() as $index) {
            $columns = '[\'' . join('\', \'', $index->getColumns()) . '\']';
            $source->addString("    ->dropIndex({$columns})");
        }
    }

    /**
     * @param Source     $source
     * @param Comparator $comparator
     * @param string     $prefix Database isolation prefix.
     */
    protected function revertForeigns(Source $source, Comparator $comparator, $prefix = '')
    {
        foreach ($comparator->droppedForeigns() as $foreign) {
            $column = "'{$foreign->getColumn()}'";
            $table = "'" . substr($foreign->getForeignTable(), strlen($prefix)) . "'";
            $key = "'{$foreign->getForeignKey()}'";

            $source->addString(
                "    ->addForeignKey({$column}, {$table}, {$key}, " . $this->foreignOptions($foreign) . ")"
            );
        }

        foreach ($comparator->alteredForeigns() as $pair) {
            /**
             * @var AbstractReference $foreign
             */
            $foreign = $pair[self::ORIGINAL_STATE];

            $column = "'{$foreign->getColumn()}'";
            $table = "'" . substr($foreign->getForeignTable(), strlen($prefix)) . "'";
            $key = "'{$foreign->getForeignKey()}'";

            $source->addString(
                "    ->alterForeignKey({$column}, {$table}, {$key}, " . $this->foreignOptions($foreign) . ")"
            );
        }

        foreach ($comparator->addedForeigns() as $foreign) {
            $column = "'{$foreign->getColumn()}'";
            $source->addString("    ->dropForeignKey({$column})");
        }
    }

    /**
     * @param AbstractTable $table
     * @return string
     */
    protected function tableAlias(AbstractTable $table)
    {
        if (empty($this->aliaser)) {
            return "'{$table->getName()}'";
        }

        $name = $this->aliaser->getTable($table);
        $database = $this->aliaser->getDatabase($table);

        if (empty($database)) {
            return "'{$name}'";
        }

        return "'{$name}', '{$database}'";
    }

    /**
     * @param AbstractColumn $column
     * @return string
     */
    private function columnOptions(AbstractColumn $column)
    {
        $options = [];

        if (!empty($column->getEnumValues())) {
            $options['values'] = $column->getEnumValues();
        }

        if (!empty($column->getSize()) && $column->phpType() == AbstractColumn::STRING) {
            $options['size'] = $column->getSize();
        }

        if (!empty($column->getScale())) {
            $options['scale'] = $column->getScale();
        }

        if (!empty($column->getPrecision())) {
            $options['precision'] = $column->getPrecision();
        }

        if ($column->isNullable()) {
            $options['null'] = true;
        }

        if ($column->hasDefaultValue() && !is_null($column->getDefaultValue())) {
            $options['default'] = $column->getDefaultValue();
        }

        return $this->mountIndents($this->serializer->serialize($options));
    }

    /**
     * @param AbstractIndex $index
     * @return string
     */
    private function indexOptions(AbstractIndex $index)
    {
        $options = [];

        if ($index->isUnique()) {
            $options['unique'] = true;
        }

        return $this->mountIndents($this->serializer->serialize($options));
    }

    /**
     * @param AbstractReference $reference
     * @return string
     */
    private function foreignOptions(AbstractReference $reference)
    {
        $options = [
            'delete' => $reference->getDeleteRule(),
            'update' => $reference->getUpdateRule()
        ];

        return $this->mountIndents($this->serializer->serialize($options));
    }

    /**
     * Mount indents for column and index options.
     *
     * @param $serialized
     * @return string
     */
    private function mountIndents($serialized)
    {
        $lines = explode("\n", $serialized);
        foreach ($lines as &$line) {
            $line = "    " . $line;
            unset($line);
        }

        return ltrim(join("\n", $lines));
    }
}