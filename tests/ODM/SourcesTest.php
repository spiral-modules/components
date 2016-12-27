<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use MongoDB\BSON\ObjectID;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\ODM\ODM;
use Spiral\Tests\ODM\Fixtures\Moderator;
use Spiral\Tests\ODM\Fixtures\ModeratorSource;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class SourcesTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testGetSource()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();

        $builder->addSchema($this->makeSchema(User::class));
        $odm->setSchema($builder);

        $source = $odm->source(User::class);
        $this->assertInstanceOf(DocumentSource::class, $source);
    }

    public function testGetCustomSource()
    {
        $builder = $this->makeBuilder();
        $odm = $this->makeODM();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Moderator::class));

        $builder->addSource(Moderator::class, ModeratorSource::class);
        $odm->setSchema($builder);

        $source = $odm->source(User::class);
        $this->assertInstanceOf(DocumentSource::class, $source);

        $source = $odm->source(Moderator::class);
        $this->assertInstanceOf(ModeratorSource::class, $source);
    }

    public function testCreate()
    {
        $odm = m::mock(ODM::class);
        $entity = m::mock(User::class);

        $source = new DocumentSource('sample-class', $odm);

        $odm->shouldReceive('instantiate')
            ->with('sample-class', ['fields'], true)
            ->andReturn($entity);

        $this->assertSame($entity, $source->create(['fields']));
    }

    public function testGetSelector()
    {
        $odm = m::mock(ODM::class);
        $selector = m::mock(DocumentSelector::class);

        $source = new DocumentSource('sample-class', $odm);

        $odm->shouldReceive('selector')
            ->with('sample-class')
            ->andReturn($selector);

        $this->assertNotSame($selector, $source->getIterator());
        $this->assertEquals($selector, $source->getIterator());
    }

    public function testSetSelector()
    {
        $odm = m::mock(ODM::class);
        $selector = m::mock(DocumentSelector::class);

        //To make it different
        $selector2 = m::mock(DocumentSelector::class);
        $selector2->shouldReceive('something');

        $source = new DocumentSource('sample-class', $odm);

        $odm->shouldReceive('selector')
            ->with('sample-class')
            ->andReturn($selector);

        $this->assertNotSame($selector, $source->getIterator());
        $this->assertEquals($selector, $source->getIterator());

        $source = $source->withSelector($selector2);

        $this->assertNotEquals($selector, $source->getIterator());
        $this->assertEquals($selector2, $source->getIterator());
    }

    public function testFindOne()
    {
        $odm = m::mock(ODM::class);
        $selector = m::mock(DocumentSelector::class);

        $entity = m::mock(User::class);
        $source = new DocumentSource('sample-class', $odm);

        $odm->shouldReceive('selector')->with('sample-class')->andReturn($selector);

        $selector->shouldReceive('sortBy')->with(['some-sort'])->andReturnSelf();
        $selector->shouldReceive('findOne')->with(['some-query'])->andReturn($entity);

        $result = $source->findOne(['some-query'], ['some-sort']);
        $this->assertSame($entity, $result);
    }

    public function testFindByPK()
    {
        $odm = m::mock(ODM::class);
        $selector = m::mock(DocumentSelector::class);

        $entity = m::mock(User::class);
        $source = new DocumentSource('sample-class', $odm);

        $odm->shouldReceive('selector')->with('sample-class')->andReturn($selector);

        $selector->shouldReceive('sortBy')->with([])->andReturnSelf();
        $selector->shouldReceive('findOne')->with([
            '_id' => new ObjectID('507f1f77bcf86cd799439011')
        ])->andReturn($entity);

        $result = $source->findByPK('507f1f77bcf86cd799439011');
        $this->assertSame($entity, $result);
    }
}