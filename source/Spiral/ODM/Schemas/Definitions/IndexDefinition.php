<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Schemas\Definitions;

/**
 * Index definition options.
 */
final class IndexDefinition
{
    /**
     * @var array
     */
    private $index;

    /**
     * @var array
     */
    private $options;

    /**
     * @param array $index
     * @param array $options
     */
    public function __construct(array $index, array $options = [])
    {
        $this->index = $index;
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getIndex(): array
    {
        return $this->index;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * So we can compare indexes.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode([
            $this->index,
            $this->options
        ]);
    }
}