<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Schemas;

class NullLocator implements LocatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function locateSchemas(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function locateSources(): array
    {
        return [];
    }
}