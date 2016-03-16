<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Pagination;

use Interop\Container\ContainerInterface;
use Mockery as m;
use Spiral\Core\Component;
use Spiral\Pagination\PaginableInterface;
use Spiral\Pagination\PaginatorInterface;
use Spiral\Pagination\PaginatorsInterface;
use Spiral\Pagination\Traits\PaginatorTrait;
use Spiral\Tests\Core\Fixtures\SampleComponent;

class TraitTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        SampleComponent::shareContainer(null);
    }

    public function testMethods()
    {
        $paginable = new PaginableClass(100);

        $this->assertSame(100, $paginable->count());

        $this->assertSame(0, $paginable->getLimit());
        $this->assertSame(0, $paginable->getOffset());

        $this->assertSame($paginable, $paginable->limit(200)->offset(100));

        $this->assertSame(200, $paginable->getLimit());
        $this->assertSame(100, $paginable->getOffset());

        $this->assertFalse($paginable->isPaginated());
    }

    /**
     * @expectedException \Spiral\Pagination\Exceptions\PaginationException
     * @expectedExceptionMessage Unable to get paginator, no paginator were set
     */
    public function testNoPaginator()
    {
        $paginable = new PaginableClass(100);

        $this->assertFalse($paginable->isPaginated());
        $paginable->getPaginator();
    }

    public function testManualPagination()
    {
        $paginator = m::mock(PaginatorInterface::class);

        $paginable = new PaginableClass(110);

        $this->assertFalse($paginable->isPaginated());
        $this->assertSame(110, $paginable->count());

        $paginable->setPaginator($paginator);

        $this->assertTrue($paginable->isPaginated());
        $this->assertSame($paginator, $paginable->getPaginator());

        $this->assertSame(0, $paginable->getLimit());
        $this->assertSame(0, $paginable->getOffset());

        $paginator->shouldReceive('paginate')->with(
            m::on(function (PaginableInterface $p) {
                $p->limit(25)->offset(50);

                return $p->count() == 110;
            })
        );

        //Delayed
        $paginable->runPagination();

        $this->assertSame(25, $paginable->getLimit());
        $this->assertSame(50, $paginable->getOffset());
    }

    /**
     * @expectedException \Spiral\Core\Exceptions\SugarException
     * @expectedExceptionMessage Unable to create paginator, PaginatorsInterface binding is missing
     *                           or container is set
     */
    public function testNoContainer()
    {
        $paginable = new PaginableClass(110);
        $paginable->paginate();
    }

    public function testResolvedWithContainer()
    {
        $container = m::mock(ContainerInterface::class);
        SampleComponent::shareContainer($container);

        $paginable = new PaginableClass(110);
        $this->assertFalse($paginable->isPaginated());

        $paginator = m::mock(PaginatorInterface::class);
        $paginator->shouldReceive('paginate')->with(
            m::on(function (PaginableInterface $p) {
                $p->limit(25)->offset(50);

                return $p->count() == 110;
            })
        );

        $container->shouldReceive('has')->with(PaginatorsInterface::class)->andReturn(true);
        $container->shouldReceive('get')->with(PaginatorsInterface::class)->andReturn(
            $paginators = m::mock(PaginatorsInterface::class)
        );

        $paginators->shouldReceive('getPaginator')->with('page', 25)->andReturn($paginator);

        $paginable->paginate(25, 'page');

        //Delayed
        $paginable->runPagination();

        $this->assertSame(25, $paginable->getLimit());
        $this->assertSame(50, $paginable->getOffset());
    }
}

class PaginableClass extends Component implements PaginableInterface
{
    use PaginatorTrait;

    private $count;

    public function __construct($count)
    {
        $this->count = $count;
    }

    public function count()
    {
        return $this->count;
    }

    public function runPagination()
    {
        $this->applyPagination();
    }
}