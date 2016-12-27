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
use Spiral\ORM\Configs\RelationsConfig;
use Spiral\ORM\Entities\RecordInstantiator;

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
     * @invisible
     * @var RelationsConfig
     */
    private $relationsConfig;

    /**
     * @param ReflectionEntity $reflection
     * @param MutatorsConfig   $mutators
     * @param RelationsConfig  $relations
     */
    public function __construct(
        ReflectionEntity $reflection,
        MutatorsConfig $mutators,
        RelationsConfig $relations
    ) {
        $this->reflection = $reflection;
        $this->mutatorsConfig = $mutators;
        $this->relationsConfig = $relations;
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
        return $this->reflection->getConstant('INSTANTIATOR') ?? RecordInstantiator::class;
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
     * {@inheritdoc}
     *
     * //todo: do we need it?
     */
    public function getFields(): array
    {
        $fields = $this->reflection->getFields();

        foreach ($fields as $field => $type) {
            if ($this->isRelation($type)) {
                unset($fields[$field]);
            }
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function defineTable(AbstractTable $table): AbstractTable
    {
        return $table;
    }

    /**
     * {@inheritdoc}
     */
    public function packSchema(SchemaBuilder $builder): array
    {
        return [];
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
}