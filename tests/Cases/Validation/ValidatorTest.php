<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Cases\Validation;

use Spiral\Core\Configurator;
use Spiral\Core\Container;
use Spiral\Core\ContainerInterface;
use Spiral\Validation\Validator;

class ValidatorTest extends \PHPUnit_Framework_TestCase
{
    public function testField()
    {
        $validator = $this->validator([
            'field' => 'value'
        ]);

        $this->assertEquals('value', $validator->field('field'));
        $this->assertEquals('DEFAULT VALUE', $validator->field('undefined', 'DEFAULT VALUE'));
    }

    /**
     * @param array                   $data
     * @param array                   $rules
     * @param array                   $config
     * @param ContainerInterface|null $container
     * @return Validator
     */
    protected function validator(
        array $data = [],
        array $rules = [],
        array $config = [],
        ContainerInterface $container = null
    ) {
        return new Validator(
            $data,
            $rules,
            !empty($container) ? $container : new Container(),
            new Configurator($config)
        );
    }
}