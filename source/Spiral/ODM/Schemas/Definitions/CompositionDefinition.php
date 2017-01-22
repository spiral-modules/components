<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\ODM\Schemas\Definitions;

/**
 * Composition definition.
 */
final class CompositionDefinition
{
    /**
     * Composition type, see DocumentEntity::ONE and DocumentEntity::MANY.
     *
     * @var int
     */
    private $type;

    /**
     * Nested class.
     *
     * @var string
     */
    private $class;

    /**
     * @param int    $type
     * @param string $class
     */
    public function __construct(int $type, string $class)
    {
        $this->type = $type;
        $this->class = $class;
    }

    /**
     * Composition type, see Document::ONE and Document::MANY.
     *
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Nested class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return array
     */
    public function packSchema(): array
    {
        return [$this->type, $this->class];
    }
}