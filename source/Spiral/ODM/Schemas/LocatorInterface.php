<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Schemas;

interface LocatorInterface
{
    /**
     * Locate all available document schemas in a project.
     *
     * @return SchemaInterface[]
     */
    public function locateSchemas(): array;

    /**
     * Locate all DocumentSources defined by user. Must return values in a form of
     * Document::class => Source::class.
     *
     * @return array
     */
    public function locateSources(): array;
}