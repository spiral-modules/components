<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Core;

final class NullMemory implements MemoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function loadData(string $section, string $location = null)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function saveData(string $section, $data, string $location = null)
    {
        //Nothing to do
    }
}