<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Validation\Configs;

use Spiral\Core\InjectableConfig;
use Spiral\Core\Traits\Config\AliasTrait;

/**
 * Validation rules and checkers config.
 */
class ValidatorConfig extends InjectableConfig
{
    use AliasTrait;

    /**
     * Configuration section.
     */
    const CONFIG = 'validation';

    /**
     * @var array
     */
    protected $config = [
        'emptyConditions' => [],
        'checkers'        => [],
        'aliases'         => []
    ];

    /**
     * @param mixed $condition
     * @return bool
     */
    public function emptyCondition($condition)
    {
        return in_array($condition, $this->config['emptyConditions']);
    }

    /**
     * @param string $checker
     * @return bool
     */
    public function hasChecker($checker)
    {
        return isset($this->config['checkers'][$checker]);
    }

    /**
     * @param string $checker
     * @return string
     * @return string
     */
    public function checkerClass($checker)
    {
        return $this->config['checkers'][$checker];
    }
}