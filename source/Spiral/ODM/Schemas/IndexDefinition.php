<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Schemas;

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
}