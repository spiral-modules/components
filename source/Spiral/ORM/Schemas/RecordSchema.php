<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ORM\Configs\MutatorsConfig;
use Spiral\ORM\Entities\RecordInstantiator;
use Spiral\ORM\Exceptions\DefinitionException;
use Spiral\ORM\Helpers\ColumnRenderer;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Definitions\IndexDefinition;

class RecordSchema implements SchemaInterface
{
    /**
     * @var ReflectionEntity
     */
    private $reflection;

    /**
     * @invisible
     * @var MutatorsConfig
     */
    private $mutatorsConfig;

    /**
     * @var ColumnRenderer
     */
    private $renderer;

    /**
     * @param ReflectionEntity    $reflection
     * @param MutatorsConfig      $mutators
     * @param ColumnRenderer|null $rendered
     */
    public function __construct(
        ReflectionEntity $reflection,
        MutatorsConfig $mutators,
        ColumnRenderer $rendered = null
    ) {
        $this->reflection = $reflection;
        $this->mutatorsConfig = $mutators;
        $this->renderer = $rendered ?? new ColumnRenderer();
    }

    /**
     * {@inheritdoc}
     */
    public function getClass(): string
    {
        return $this->reflection->getName();
    }

    /**
     * @return ReflectionEntity
     */
    public function getReflection(): ReflectionEntity
    {
        return $this->reflection;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstantiator(): string
    {
        return $this->reflection->getProperty('instantiator') ?? RecordInstantiator::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase()
    {
        $database = $this->reflection->getProperty('database');
        if (empty($database)) {
            //Empty database to be used
            return null;
        }

        return $database;
    }

    /**
     * {@inheritdoc}
     */
    public function getTable(): string
    {
        $table = $this->reflection->getProperty('table');
        if (empty($table)) {
            //Generate collection using short class name
            $table = Inflector::camelize($this->reflection->getShortName());
            $table = Inflector::pluralize($table);
        }

        return $table;
    }

    /**
     * Returns set of declared indexes.
     *
     * Example:
     * const INDEXES = [
     *      [self::UNIQUE, 'email'],
     *      [self::INDEX, 'status', 'balance'],
     *      [self::INDEX, 'public_id']
     * ];
     *
     * @do generator
     *
     * @return \Generator|IndexDefinition[]
     *
     * @throws DefinitionException
     */
    public function getIndexes(): \Generator
    {
        $definitions = $this->reflection->getProperty('indexes') ?? [];

        foreach ($definitions as $definition) {
            yield $this->castIndex($definition);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function renderTable(AbstractTable $table): AbstractTable
    {
        return $this->renderer->renderColumns(
            $this->getFields(),
            $this->createDefaults(),
            $table
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getRelations(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function packSchema(SchemaBuilder $builder, AbstractTable $table = null): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFields(): array
    {
        $fields = $this->reflection->getSchema();

        foreach ($fields as $field => $type) {
            if ($this->isRelation($type)) {
                unset($fields[$field]);
            }
        }

        return $fields;
    }

    /**
     * Check if field schema/type defines relation.
     *
     * @param mixed $type
     *
     * @return bool
     */
    protected function isRelation($type): bool
    {
        if (is_array($type)) {
            return true;
        }

        return false;
    }

    /**
     * @param array $definition
     *
     * @return IndexDefinition
     *
     * @throws DefinitionException
     */
    protected function castIndex(array $definition)
    {
        $unique = null;
        $columns = [];

        foreach ($definition as $chunk) {
            if ($chunk == Record::INDEX || $chunk == Record::UNIQUE) {
                $unique = $chunk === Record::UNIQUE;
                continue;
            }

            $columns[] = $chunk;
        }

        if (is_null($unique)) {
            throw new DefinitionException(
                "Record '{$this}' has index definition with unspecified index type"
            );
        }

        if (empty($columns)) {
            throw new DefinitionException(
                "Record '{$this}' has index definition without any column associated to"
            );
        }

        return new IndexDefinition($columns, $unique);
    }

    /**
     * Default defined values.
     *
     * @return array
     */
    protected function createDefaults(): array
    {
        //Process defaults
        return $this->reflection->getProperty('defaults') ?? [];
    }
}