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
use Spiral\ODM\Helpers\AggregationHelper;
use Spiral\ODM\MongoManager;
use Spiral\Tests\ODM\Fixtures\GoodAggregates;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class AggregationsTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    /**
     * @expectedException \Spiral\ODM\Exceptions\DocumentException
     * @expectedExceptionMessage Undefined method call 'badAggregation' in 'Spiral\Tests\ODM\Fixtures\GoodAggregates'
     */
    public function testUndefinedAggregationOrMethod()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($aggregates = $this->makeSchema(GoodAggregates::class));
        $builder->addSchema($this->makeSchema(User::class));

        $manager = m::mock(MongoManager::class);
        $odm = $this->makeODM($manager);
        $odm->buildSchema($builder);

        $aggr = $odm->make(GoodAggregates::class, [
            'userId' => new ObjectID('507f1f77bcf86cd799439011')
        ]);

        $aggr->badAggregation();
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\AggregationException
     * @expectedExceptionMessage Aggregation method call except 0 parameters
     */
    public function testGetAggregationWithArguments()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($aggregates = $this->makeSchema(GoodAggregates::class));
        $builder->addSchema($this->makeSchema(User::class));

        $manager = m::mock(MongoManager::class);
        $odm = $this->makeODM($manager);
        $odm->buildSchema($builder);

        $aggr = $odm->make(GoodAggregates::class, [
            'userId' => new ObjectID('507f1f77bcf86cd799439011')
        ]);

        $aggr->users(123);
    }

    public function testAggregationHelper()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($aggregates = $this->makeSchema(GoodAggregates::class));
        $builder->addSchema($this->makeSchema(User::class));

        $manager = m::mock(MongoManager::class);
        $odm = $this->makeODM($manager);
        $odm->buildSchema($builder);

        $aggr = $odm->make(GoodAggregates::class, [
            'userId' => new ObjectID('507f1f77bcf86cd799439011')
        ]);

        $odm = m::mock($odm)->makePartial();
        $helper = new AggregationHelper($aggr, $odm);

        $selector = m::mock(DocumentSelector::class);

        $odm->shouldReceive('selector')->with(User::class)->andReturn($selector);
        $selector->shouldReceive('where')->with([])->andReturnSelf();

        $this->assertSame($selector, $helper->createAggregation('users'));
    }

    public function testAggregationHelperWithQueryTemplate()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($aggregates = $this->makeSchema(GoodAggregates::class));
        $builder->addSchema($this->makeSchema(User::class));

        $manager = m::mock(MongoManager::class);
        $odm = $this->makeODM($manager);
        $odm->buildSchema($builder);

        $aggr = $odm->make(GoodAggregates::class, [
            'userId' => new ObjectID('507f1f77bcf86cd799439011')
        ]);

        $odm = m::mock($odm)->makePartial();
        $helper = new AggregationHelper($aggr, $odm);
        $selector = m::mock(DocumentSelector::class);

        $user = m::mock(User::class);

        $odm->shouldReceive('selector')->with(User::class)->andReturn($selector);
        $selector->shouldReceive('where')->with([
            '_id' => new ObjectID('507f1f77bcf86cd799439011')
        ])->andReturnSelf();
        $selector->shouldReceive('findOne')->andReturn($user);

        $this->assertSame($user, $helper->createAggregation('user'));
    }
}