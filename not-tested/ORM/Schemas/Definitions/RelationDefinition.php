<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas\Definitions;

/**
 * Defines relation in schema.
 */
final class RelationDefinition
{
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
     * @param string $type
     * @param string $target
     * @param array  $options
     * @param bool   $inverse
     */
    public function __construct(string $type, string $target, array $options, bool $inverse = false)
    {
        $this->type = $type;
        $this->target = $target;
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
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
     * @return bool
     */
    public function needInverse(): bool
    {
        return $this->inverse;
    }
}