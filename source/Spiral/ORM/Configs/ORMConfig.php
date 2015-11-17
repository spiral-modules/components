<?php
/**
 * Spiral Framework.
 *
 * @license MIT
 * @author  Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Configs;

use Spiral\Core\ArrayConfig;

/**
 * Translation component configuration.
 */
class ORMConfig extends ArrayConfig
{
    /**
     * Configuration section.
     */
    const CONFIG = 'orm';

    /**
     * @var array
     */
    protected $config = [
        'mutators'       => [],
        'mutatorAliases' => [],
        'relations'      => []
    ];

    /**
     * @param string $type
     * @param string $target
     * @return bool
     */
    public function hasRelation($type, $target = 'class')
    {
        return isset($this->config['relations'][$type][$target]);
    }

    /**
     * @param string $type
     * @param string $target
     * @return string
     */
    public function relationClass($type, $target)
    {
        return $this->config['relations'][$type][$target];
    }

    /**
     * Resolve mutator alias.
     *
     * @param string $mutator
     * @return string
     */
    public function mutatorAlias($mutator)
    {
        if (!is_string($mutator) || !isset($this->config['mutatorAliases'][$mutator])) {
            return $mutator;
        }

        return $this->config['mutatorAliases'][$mutator];
    }

    /**
     * Get list of mutators associated with given type.
     *
     * @param string $type
     * @return array
     */
    public function getMutators($type)
    {
        return isset($this->config['mutators'][$type]) ? $this->config['mutators'][$type] : [];
    }
}