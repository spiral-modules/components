<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\ODM\Schemas\Definitions;

/**
 * Aggregation definition (links one Document with another).
 */
final class AggregationDefinition
{
    /**
     * Aggregation type, see Document::ONE and Document::MANY.
     *
     * @var int
     */
    private $type;

    /**
     * Linked class.
     *
     * @var string
     */
    private $class;

    /**
     * Query template.
     *
     * @var array
     */
    private $query = [];

    /**
     * @param int    $type
     * @param string $class
     * @param array  $query
     */
    public function __construct(int $type, string $class, array $query)
    {
        $this->type = $type;
        $this->class = $class;
        $this->query = $query;
    }

    /**
     * Aggregation type, see Document::ONE and Document::MANY.
     *
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Linked class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Query template.
     *
     * @return array
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function packSchema(): array
    {
        return [$this->type, $this->class, $this->query];
    }
}