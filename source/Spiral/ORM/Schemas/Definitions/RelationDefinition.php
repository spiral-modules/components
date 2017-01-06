<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas\Definitions;

use Spiral\ORM\Exceptions\SchemaException;

/**
 * Defines relation in schema.
 */
final class RelationDefinition
{
    /**
     * Relation name.
     *
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $target;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var bool
     */
    private $inverse = false;

    /**
     * Defines where relation comes from.
     *
     * @var RelationContext
     */
    private $sourceContext;

    /**
     * Defines where realation points to (if any).
     *
     * @var RelationContext|null
     */
    private $targetContext;

    /**
     * @param string $name
     * @param string $type
     * @param string $target
     * @param array  $options
     * @param bool   $inverse
     */
    public function __construct(
        string $name,
        string $type,
        string $target,
        array $options,
        bool $inverse = false
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->target = $target;
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Target class name, see more information about target context via targetContext() method.
     *
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Source context (where relation comes from).
     *
     * @return RelationContext
     */
    public function sourceContext(): RelationContext
    {
        if (empty($this->sourceContext)) {
            throw new SchemaException("Source context not set");
        }

        return $this->sourceContext;
    }

    /**
     * Target context if any.
     *
     * @return null|RelationContext
     */
    public function targetContext()
    {
        return $this->targetContext;
    }

    /**
     * Set relation contexts.
     *
     * @param RelationContext      $source
     * @param RelationContext|null $target
     *
     * @return RelationDefinition
     */
    public function withContext(RelationContext $source, RelationContext $target = null): self
    {
        $definition = clone $this;
        $definition->sourceContext = $source;
        $definition->targetContext = $target;

        return $definition;
    }

    /**
     * Create version of definition with different set of options.
     *
     * @param array $options
     *
     * @return RelationDefinition
     */
    public function withOptions(array $options): self
    {
        $definition = clone $this;
        $definition->options = $options;

        return $definition;
    }

    /**
     * @todo: add inversion options
     * @return bool
     */
    public function needInverse(): bool
    {
        return $this->inverse;
    }

//    /**
//     * Will specify missing fields in relation definition using default definition options. Such
//     * options are dynamic and populated based on values fetched from related records.
//     */
//    protected function clarifyDefinition()
//    {
//        foreach ($this->defaultDefinition as $property => $pattern) {
//            if (isset($this->definition[$property])) {
//                //Specified by user
//                continue;
//            }
//
//            if (!is_string($pattern)) {
//                //Some options are actually array of options
//                $this->definition[$property] = $pattern;
//                continue;
//            }
//
//            //Let's create option value using default proposer values
//            $this->definition[$property] = \Spiral\interpolate(
//                $pattern,
//                $this->proposedDefinitions()
//            );
//        }
//    }

//    /**
//     * Create set of options to specify missing relation definition fields.
//     *
//     * @return array
//     */
//    protected function proposedDefinitions()
//    {
//        $options = [
//            //Relation name
//            'name'              => $this->name,
//            //Relation name in plural form
//            'name:plural'       => Inflector::pluralize($this->name),
//            //Relation name in singular form
//            'name:singular'     => Inflector::singularize($this->name),
//            //Parent record role name
//            'record:role'       => $this->record->getRole(),
//            //Parent record table name
//            'record:table'      => $this->record->getTable(),
//            //Parent record primary key
//            'record:primaryKey' => $this->record->getPrimaryKey(),
//        ];
//
//        //Some options may use values declared in other definition fields
//        $proposed = [
//            RecordEntity::OUTER_KEY   => 'outerKey',
//            RecordEntity::INNER_KEY   => 'innerKey',
//            RecordEntity::PIVOT_TABLE => 'pivotTable',
//        ];
//
//        foreach ($proposed as $property => $alias) {
//            if (isset($this->definition[$property])) {
//                //Let's create some default options based on user specified values
//                $options['definition:' . $alias] = $this->definition[$property];
//            }
//        }
//
//        if ($this->builder->hasRecord($this->target)) {
//            $options = $options + [
//                    //Outer role name
//                    'outer:role'       => $this->outerRecord()->getRole(),
//                    //Outer record table
//                    'outer:table'      => $this->outerRecord()->getTable(),
//                    //Outer record primary key
//                    'outer:primaryKey' => $this->outerRecord()->getPrimaryKey(),
//                ];
//        }
//
//        return $options;
//    }

//    /**
//     * Resolve correct abstract type to represent inner or outer key. Primary types will be
//     * converted to appropriate sized integers.
//     *
//     * @param AbstractColumn $column
//     *
//     * @return string
//     */
//    protected function resolveAbstract(AbstractColumn $column)
//    {
//        switch ($column->abstractType()) {
//            case 'bigPrimary':
//                return 'bigInteger';
//            case 'primary':
//                return 'integer';
//            default:
//                //Not primary key
//                return $column->abstractType();
//        }
//    }

}