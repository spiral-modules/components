<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models;

use Spiral\Models\Traits\EventsTrait;

/**
 * Entity with ability to alter it's behaviour using set of statically assigned events.
 */
class MutableObject
{
    use EventsTrait;

    /**
     * Every entity might have set of traits which can be initiated manually or at moment of
     * construction model instance. Array will store already initiated model names.
     *
     * @var array
     */
    private static $initiated = [];

    /**
     * Initialize entity state.
     */
    public function __construct()
    {
        self::initialize(false);
    }

    /**
     * Clear initiated objects list.
     */
    public static function resetInitiated()
    {
        self::$initiated = [];
    }

    /**
     * Initiate associated model traits. System will look for static method with "__init__" prefix.
     * Attention, trait must.
     *
     * @param bool $analysis Must be set to true while static reflection analysis.
     */
    protected static function initialize($analysis = false)
    {
        $state = $class = static::class;

        if ($analysis) {
            //Normal and initialization for analysis must load different methods
            $state = "{$class}~";

            $prefix = '__describe__';
        } else {
            $prefix = '__init__';
        }

        if (isset(self::$initiated[$state])) {
            //Already initiated (not for analysis)
            return;
        }

        foreach (get_class_methods($class) as $method) {
            if (strpos($method, $prefix) === 0) {
                forward_static_call(['static', $method]);
            }
        }

        self::$initiated[$state] = true;
    }
}