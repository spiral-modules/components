<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Stempler;

/**
 * Default implementation for ContextInterface.
 */
final class StemplerSource
{
    /**
     * Must be local stream.
     *
     * @var string
     */
    private $filename;

    /**
     * @var null|string
     */
    private $source = null;

    /**
     * @param string $filename
     */
    public function __construct(string $filename, string $source = null)
    {
        $this->filename = $filename;
        $this->source = $source;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * {@inheritdoc}
     */
    public function getSource(): string
    {
        return $this->source ?? file_get_contents($this->filename);
    }
}