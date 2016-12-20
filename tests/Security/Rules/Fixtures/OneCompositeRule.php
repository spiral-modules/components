<?php
/**
 * components
 *
 * @author    Dmitry Mironov <dmitry.mironov@spiralscout.com>
 */

namespace Spiral\Tests\Security\Rules\Fixtures;


use Spiral\Security\Rules\CompositeRule;

/**
 * Class OneCompositeRule
 *
 * @package Spiral\Tests\Security\Actors
 */
class OneCompositeRule extends CompositeRule
{
    const RULES     = ['foo.create', 'bar.update', 'baz.delete'];
    const BEHAVIOUR = self::AT_LEAST_ONE;
}