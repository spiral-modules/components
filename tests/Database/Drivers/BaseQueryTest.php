<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Database\Drivers;

use Spiral\Database\Injections\FragmentInterface;

abstract class BaseQueryTest extends BaseTest
{
    /**
     * Send sample query in a form where all quotation symbols replaced with { and }.
     *
     * @param string            $query
     * @param FragmentInterface $fragment
     */
    protected function assertSameQuery(string $query, FragmentInterface $fragment)
    {
        //Preparing query
        $query = str_replace(
            ['{', '}'],
            explode('.', $this->database()->getDriver()->identifier('.')),
            $query
        );

        $this->assertSame(
            preg_replace('/\s+/', '', $query),
            preg_replace('/\s+/', '', $fragment->sqlStatement())
        );
    }
}