<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Validation\Config;

use Spiral\Core\ArrayConfig;

/**
 * Validation rules and checkers config.
 */
class ValidatorConfig extends ArrayConfig
{
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
     * @param mixed $condition
     * @return mixed
     */
    public function resolveCondition($condition)
    {
        if (is_string($condition) && isset($this->config['aliases'][$condition])) {
            //Condition were aliased
            $condition = $this->config['aliases'][$condition];
        }

        return $condition;
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