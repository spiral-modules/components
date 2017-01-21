<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Migrations\Atomizer;

use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractIndex;
use Spiral\Database\Schemas\Prototypes\AbstractReference;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Database\Schemas\StateComparator;
use Spiral\Reactor\Body\Source;
use Spiral\Reactor\Traits\SerializerTrait;

class MigrationRenderer implements RendererInterface
{
    use SerializerTrait;

    /**
     * Comparator alteration states.
     */
    const NEW_STATE      = 0;
    const ORIGINAL_STATE = 1;

    /**
     * @var AliasLookup
     */
    private $lookup;

    /**
     * @param AliasLookup $lookup
     */
    public function __construct(AliasLookup $lookup)
    {
        $this->lookup = $lookup;
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(Source $source, AbstractTable $table)
    {
        //Get table blueprint
        $source->addLine("\$this->table({$this->tableAlias($table)})");
        $comparator = $table->getComparator();

        $this->declareColumns($source, $comparator);
        $this->declareIndexes($source, $comparator);
        $this->declareForeigns($source, $comparator, $table->getPrefix());

        //Finalization
        $source->addLine("    ->create();");
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable(Source $source, AbstractTable $table)
    {
        //Get table blueprint
        $source->addLine("\$this->table({$this->tableAlias($table)})");
        $comparator = $table->getComparator();

        if ($table->getInitialName() != $table->getName()) {
            //Renamig table
            $source->addLine("    ->renameTable({$this->lookup->tableAlias($table)})");
        }

        $this->declareColumns($source, $comparator);
        $this->declareIndexes($source, $comparator);
        $this->declareForeigns($source, $comparator, $table->getPrefix());

        //Finalization
        $source->addLine("    ->update();");
    }

    /**
     * {@inheritdoc}
     */
    public function revertTable(Source $source, AbstractTable $table)
    {
        //Get table blueprint
        $source->addLine("\$this->table({$this->tableAlias($table)})");
        $comparator = $table->getComparator();

        $this->revertForeigns($source, $comparator, $table->getPrefix());
        $this->revertIndexes($source, $comparator);
        $this->revertColumns($source, $comparator);

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
     * @param Source          $source
     * @param StateComparator $comparator
     */
    protected function declareColumns(Source $source, StateComparator $comparator)
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
     * @param Source          $source
     * @param StateComparator $comparator
     */
    protected function declareIndexes(Source $source, StateComparator $comparator)
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
     * @param Source          $source
     * @param StateComparator $comparator
     * @param string          $prefix Database isolation prefix
     */
    protected function declareForeigns(
        Source $source,
        StateComparator $comparator,
        string $prefix = ''
    ) {
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
     * @param Source          $source
     * @param StateComparator $comparator
     */
    protected function revertColumns(Source $source, StateComparator $comparator)
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
     * @param Source          $source
     * @param StateComparator $comparator
     */
    protected function revertIndexes(Source $source, StateComparator $comparator)
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
     * @param Source          $source
     * @param StateComparator $comparator
     * @param string          $prefix Database isolation prefix.
     */
    protected function revertForeigns(
        Source $source,
        StateComparator $comparator,
        string $prefix = ''
    ) {
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
     * @param AbstractColumn $column
     *
     * @return string
     */
    private function columnOptions(AbstractColumn $column): string
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

        return $this->mountIndents($this->getSerializer()->serialize($options));
    }

    /**
     * @param AbstractIndex $index
     *
     * @return string
     */
    private function indexOptions(AbstractIndex $index): string
    {
        $options = [];

        if ($index->isUnique()) {
            $options['unique'] = true;
        }

        return $this->mountIndents($this->getSerializer()->serialize($options));
    }

    /**
     * @param AbstractReference $reference
     *
     * @return string
     */
    private function foreignOptions(AbstractReference $reference): string
    {
        $options = [
            'delete' => $reference->getDeleteRule(),
            'update' => $reference->getUpdateRule()
        ];

        return $this->mountIndents($this->getSerializer()->serialize($options));
    }

    /**
     * Mount indents for column and index options.
     *
     * @param $serialized
     *
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

    /**
     * @param AbstractTable $table
     *
     * @return string
     */
    protected function tableAlias(AbstractTable $table): string
    {
        return "'{$this->lookup->tableAlias($table)}', '{$this->lookup->databaseAlias($table)}'";
    }
}