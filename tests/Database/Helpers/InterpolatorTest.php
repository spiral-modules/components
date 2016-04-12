<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Database\Helpers;

use Spiral\Database\Helpers\QueryInterpolator;
use Spiral\Database\Injections\Parameter;
use Spiral\Database\Injections\ParameterInterface;

class InterpolatorTest extends \PHPUnit_Framework_TestCase
{
    public function testFlattening()
    {
        $parameters = [
            ':named' => new Parameter('test'),
            new Parameter([1, 2, 3], \PDO::PARAM_INT)
        ];

        $flattened = QueryInterpolator::flattenParameters($parameters);
        $this->assertCount(4, $flattened);

        $this->assertArrayHasKey(':named', $flattened);

        foreach ($flattened as $parameter) {
            $this->assertInstanceOf(ParameterInterface::class, $parameter);
        }

        $this->assertSame('test', $flattened[':named']->getValue());
        $this->assertSame(1, $flattened[0]->getValue());
        $this->assertSame(2, $flattened[1]->getValue());
        $this->assertSame(3, $flattened[2]->getValue());
    }

    public function testInterpolation()
    {
        $query = 'SELECT * FROM table WHERE name = :name AND id IN(?, ?, ?) AND balance > :balance';

        $parameters = [
            ':name'    => new Parameter('Anton'),
            new Parameter([1, 2, 3], \PDO::PARAM_INT),
            ':balance' => new Parameter(120),
        ];

        $interpolated = QueryInterpolator::interpolate($query, $parameters);

        $this->assertSame(
            'SELECT * FROM table WHERE name = \'Anton\' AND id IN(1, 2, 3) AND balance > 120',
            $interpolated
        );
    }

    public function testDatesInterpolation()
    {
        $query = 'SELECT * FROM table WHERE name = :name AND id IN(?, ?, ?) AND registered > ?';

        $parameters = [
            ':name' => new Parameter('Anton'),
            new Parameter([1, 2, 3], \PDO::PARAM_INT),
            new Parameter($date = new \DateTime('now')),
        ];

        $interpolated = QueryInterpolator::interpolate($query, $parameters);

        $this->assertSame(
            'SELECT * FROM table WHERE name = \'Anton\' AND id IN(1, 2, 3) AND registered > \'' . $date->format(\DateTime::ISO8601) . '\'',
            $interpolated
        );
    }
}