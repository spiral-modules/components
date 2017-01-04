<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Configs;

use Spiral\Core\InjectableConfig;

/**
 * Defined classes and behaviours for ORM relations.
 */
class RelationsConfig extends InjectableConfig
{
    /**
     * Configuration section.
     */
    const CONFIG = 'schemas/relations';

    /**
     * @var array
     */
    protected $config = [
        'relations' => [],
    ];

    /**
     * @param string $type
     * @param string $section
     *
     * @return bool
     */
    public function hasRelation($type, string $section = 'class')
    {
        return isset($this->config['relations'][$type][$section]);
    }

    /**
     * @param string $type
     * @param string $section
     *
     * @return string
     */
    public function relationClass($type, string $section): string
    {
        return $this->config['relations'][$type][$section];
    }
}