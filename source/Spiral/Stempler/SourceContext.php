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
final class SourceContext implements SourceContextInterface
{
    /**
     * Must be local stream.
     *
     * @var string
     */
    private $filename;

    /**
     * @param string $filename
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
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
        return file_get_contents($this->filename);
    }
}