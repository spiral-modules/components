<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Models\AccessorInterface;
use Spiral\Models\Exceptions\AccessorExceptionInterface;
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
     * Fields and their types declared in Record model.
     *
     * @return array
     */
    public function getFields(): array
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
            $this->getDefaults(),
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
    public function packSchema(SchemaBuilder $builder, AbstractTable $table): array
    {
        return [
            //Default entity values
            Record::SH_DEFAULTS  => $this->packDefaults($table),

            //Entity behaviour
            Record::SH_HIDDEN    => $this->reflection->getHidden(),
            Record::SH_SECURED   => $this->reflection->getSecured(),
            Record::SH_FILLABLE  => $this->reflection->getFillable(),

            //Mutators can be altered based on ORM\SchemasConfig
            Record::SH_MUTATORS  => $this->buildMutators($table),

            //Relations
            Record::SH_RELATIONS => []
        ];
    }

    /**
     * Generate set of default values to be used by record.
     *
     * @param AbstractTable $table
     * @param array         $overwriteDefaults
     *
     * @return array
     */
    protected function packDefaults(AbstractTable $table, array $overwriteDefaults = []): array
    {
        //User defined default values
        $userDefined = $overwriteDefaults + $this->getDefaults();

        //We need mutators to normalize default values
        $mutators = $this->buildMutators($table);

        $defaults = [];
        foreach ($table->getColumns() as $column) {
            $field = $column->getName();

            if ($column->isNullable()) {
                $defaults[$field] = null;
                continue;
            }

            /**
             * working with default values!
             */

            $default = $column->getDefaultValue();

            if (array_key_exists($field, $userDefined)) {
                //No merge to keep fields order intact
                $default = $userDefined[$field];
            }

            if (array_key_exists($field, $defaults)) {
                //Default value declared in model schema
                $default = $defaults[$field];
            }

            //Let's process default value using associated setter
            if (isset($mutators[Record::MUTATOR_SETTER][$field])) {
                try {
                    $setter = $mutators[Record::MUTATOR_SETTER][$field];
                    $default = call_user_func($setter, $default);
                } catch (\Exception $exception) {
                    //Unable to generate default value, use null or empty array as fallback
                }
            }

            if (isset($mutators[Record::MUTATOR_ACCESSOR][$field])) {
                $default = $this->accessorDefault(
                    $default,
                    $mutators[Record::MUTATOR_ACCESSOR][$field]
                );
            }

            //Registering default values
            $defaults[$field] = $default;
        }

        return $defaults;
    }

    /**
     * Generate set of mutators associated with entity fields using user defined and automatic
     * mutators.
     *
     * @see MutatorsConfig
     *
     * @param AbstractTable $table
     *
     * @return array
     */
    protected function buildMutators(AbstractTable $table): array
    {
        $mutators = $this->reflection->getMutators();

        //Trying to resolve mutators based on field type
        foreach ($table->getColumns() as $column) {
            //Resolved mutators
            $resolved = [];

            if (!empty($filter = $this->mutatorsConfig->getMutators($column->abstractType()))) {
                //Mutator associated with type directly
                $resolved += $filter;
            } elseif (!empty($filter = $this->mutatorsConfig->getMutators('php:' . $column->phpType()))) {
                //Mutator associated with php type
                $resolved += $filter;
            }

            //Merging mutators and default mutators
            foreach ($resolved as $mutator => $filter) {
                if (!array_key_exists($column->getName(), $mutators[$mutator])) {
                    $mutators[$mutator][$column->getName()] = $filter;
                }
            }
        }

        return $mutators;
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
    protected function getDefaults(): array
    {
        //Process defaults
        return $this->reflection->getProperty('defaults') ?? [];
    }


    /**
     * Pass value thought accessor to ensure it's default.
     *
     * @param mixed  $default
     * @param string $accessor
     *
     * @return mixed
     *
     * @throws AccessorExceptionInterface
     */
    protected function accessorDefault($default, string $accessor)
    {
        /**
         * @var AccessorInterface $instance
         */
        $instance = new $accessor($default, [/*no context given*/]);
        $default = $instance->packValue();

        if (is_object($default)) {
            //Some accessors might want to return objects (DateTime, StorageObject), default to null
            $default = null;
        }

        return $default;
    }
}