<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Exceptions\OptionsException;
use Spiral\ORM\Helpers\RelationOptions;
use Spiral\ORM\Schemas\Definitions\RelationDefinition;
use Spiral\ORM\Schemas\RelationInterface;

/**
 * Basic class for SQL specific relations.
 */
abstract class AbstractSchema implements RelationInterface
{
    /**
     * Most of relations provides ability to specify many different configuration options, such
     * as key names, pivot table schemas, foreign key request, ability to be nullabe and etc.
     *
     * To simple schema definition in real projects we can fill some of this values automatically
     * based on some "environment" values such as parent/outer record table, role name, primary key
     * and etc.
     *
     * Example:
     * Record::INNER_KEY => '{outer:role}_{outer:primaryKey}'
     *
     * Result:
     * Outer Record is User with primary key "id" => "user_id"
     *
     * @var array
     */
    const OPTIONS_TEMPLATE = [];

    /**
     * @var RelationDefinition
     */
    protected $definition;

    /**
     * Provides ability to define missing relation options based on template.
     *
     * @see self::OPTIONS_TEMPLATE
     * @var RelationOptions
     */
    protected $options;

    /**
     * @param RelationDefinition $definition
     */
    public function __construct(RelationDefinition $definition)
    {
        $this->definition = $definition;
        $this->options = new RelationOptions($definition, static::OPTIONS_TEMPLATE);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition(): RelationDefinition
    {
        return $this->definition;
    }


    public function packRelation(): array
    {
        //todo: replace
        return [];
    }

    /**
     * Define relation configuration option.
     *
     * @param string $option
     *
     * @return mixed
     *
     * @throws OptionsException
     */
    protected function option(string $option)
    {
        return $this->options->define($option);
    }
}