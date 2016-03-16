<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Pagination;

use Mockery as m;
use Spiral\Pagination\CountingPaginator;
use Spiral\Pagination\PaginableInterface;
use Spiral\Pagination\PaginatorInterface;
use Spiral\Pagination\PredictableInterface;

class CountingPaginatorTest extends \PHPUnit_Framework_TestCase
{
    public function testInterfaces()
    {
        $paginator = new CountingPaginator(25);

        $this->assertInstanceOf(PaginatorInterface::class, $paginator);
        $this->assertInstanceOf(PredictableInterface::class, $paginator);
    }

    public function testLimit()
    {
        $paginator = new CountingPaginator(25);

        $this->assertSame(25, $paginator->getLimit());
        $paginator->setLimit(50);
        $this->assertSame(50, $paginator->getLimit());
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
        $paginator->setCount(100);

        $this->assertSame(1, $paginator->getPage());

        $this->assertSame(false, $paginator->previousPage());
        $this->assertSame(2, $paginator->nextPage());

        $this->assertSame(100, $paginator->getCount());
        $this->assertSame(0, $paginator->getOffset());
        $this->assertSame(4, $paginator->countPages());
        $this->assertSame(25, $paginator->countDisplayed());
    }

    public function testSecondPage()
    {
        $paginator = new CountingPaginator(25);
        $paginator->setCount(110);

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
        $paginator->setCount(110);

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

        $paginator->setCount(24);
        $this->assertFalse($paginator->isRequired());

        $paginator->setCount(25);
        $this->assertFalse($paginator->isRequired());

        $paginator->setCount(26);
        $this->assertTrue($paginator->isRequired());
    }

    public function testPaginate()
    {
        $paginator = new CountingPaginator(25);
        $paginable = m::mock(PaginableInterface::class);

        $paginable->shouldReceive('count')->andReturn(255);

        $paginable->shouldReceive('limit')->with(25)->andReturnSelf();
        $paginable->shouldReceive('offset')->with(4 * 25)->andReturnSelf();

        $paginator->setPage(5)->paginate($paginable);

        $this->assertSame(255, $paginator->getCount());
        $this->assertTrue($paginator->isRequired());
        $this->assertSame(5, $paginator->getPage());
        $this->assertSame(4 * 25, $paginator->getOffset());

        $this->assertSame((int)ceil(255 / 25), $paginator->countPages());
    }
}