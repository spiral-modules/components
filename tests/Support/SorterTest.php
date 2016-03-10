<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\tests\Cases\Support;

use Spiral\Support\DFSSorter;

class SorterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider stackProvider
     *
     * @param array $input
     * @param array $output
     */
    public function testSorter($input, $output)
    {
        $sorter = new DFSSorter();
        foreach ($input as $element => $dependencies) {
            $sorter->addItem($element, $element, $dependencies);
        }

        $this->assertEquals($output, $sorter->sort());
    }

    /**
     * @return array
     */
    public function stackProvider()
    {
        return [
            [
                [
                    'a' => ['c'],
                    'b' => ['a'],
                    'c' => [],
                ],
                ['c', 'a', 'b'],
            ],
            [
                [
                    'a' => ['c', 'b'],
                    'b' => ['c'],
                    'c' => [],
                ],
                ['c', 'b', 'a'],
            ],
            [
                [
                    'a' => ['c', 'b'],
                    'b' => ['c'],
                    'c' => [],
                    'd' => ['a'],
                ],
                ['c', 'b', 'a', 'd'],
            ],
        ];
    }
}
