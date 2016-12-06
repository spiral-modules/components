<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Pagination;

use Mockery as m;
use Spiral\Pagination\CountingInterface;
use Spiral\Pagination\CountingPaginator;
use Spiral\Pagination\PaginatorInterface;
use Spiral\Pagination\PredictableInterface;

class CountingPaginatorTest extends \PHPUnit_Framework_TestCase
{
    public function testInterfaces()
    {
        $paginator = new CountingPaginator(25);

        $this->assertInstanceOf(PaginatorInterface::class, $paginator);
        $this->assertInstanceOf(CountingInterface::class, $paginator);
        $this->assertInstanceOf(PredictableInterface::class, $paginator);
    }

    public function testLimit()
    {
        $paginator = new CountingPaginator(25);

        $this->assertSame(25, $paginator->getLimit());
        $newPaginator = $paginator->withLimit(50);
        $this->assertSame(25, $paginator->getLimit());
        $this->assertSame(50, $newPaginator->getLimit());
    }

    public function testCountsAndPages()
    {
        $paginator = new CountingPaginator(25);

        $this->assertSame(0, $paginator->getCount());
        $this->assertSame(1, $paginator->getPage());
        $this->assertSame(0, $paginator->getOffset());
        $this->assertSame(1, $paginator->countPages());
        $this->assertSame(0, $paginator->countDisplayed());
    }

    public function testFirstPage()
    {
        $paginator = new CountingPaginator(25);
        $paginator = $paginator->withCount(100);

        $this->assertSame(1, $paginator->getPage());

        $this->assertSame(null, $paginator->previousPage());
        $this->assertSame(2, $paginator->nextPage());

        $this->assertSame(100, $paginator->getCount());
        $this->assertSame(0, $paginator->getOffset());
        $this->assertSame(4, $paginator->countPages());
        $this->assertSame(25, $paginator->countDisplayed());
    }

    public function testSecondPage()
    {
        $paginator = new CountingPaginator(25);
        $paginator = $paginator->withCount(110);

        $this->assertSame(110, $paginator->getCount());
        $this->assertSame(1, $paginator->getPage());

        $paginator->setPage(2);

        $this->assertSame(1, $paginator->previousPage());
        $this->assertSame(3, $paginator->nextPage());

        $this->assertSame(2, $paginator->getPage());
        $this->assertSame(25, $paginator->getOffset());
        $this->assertSame(5, $paginator->countPages());
        $this->assertSame(25, $paginator->countDisplayed());
    }

    public function testLastPageNumber()
    {
        $paginator = new CountingPaginator(25);
        $paginator = $paginator->withCount(110);

        $this->assertSame(110, $paginator->getCount());
        $this->assertSame(1, $paginator->getPage());

        $paginator->setPage(100);

        $this->assertSame($paginator->countPages(), $paginator->getPage());
        $this->assertSame(
            ($paginator->getPage() - 1) * $paginator->getLimit(),
            $paginator->getOffset()
        );

        $this->assertSame(5, $paginator->countPages());
        $this->assertSame(10, $paginator->countDisplayed());
    }

    public function testIsRequired()
    {
        $paginator = new CountingPaginator(25);

        $paginator = $paginator->withCount(24);
        $this->assertFalse($paginator->isRequired());

        $paginator = $paginator->withCount(25);
        $this->assertFalse($paginator->isRequired());

        $paginator = $paginator->withCount(26);
        $this->assertTrue($paginator->isRequired());
    }
}