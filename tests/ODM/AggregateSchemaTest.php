<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use Spiral\ODM\Document;
use Spiral\ODM\Schemas\Definitions\AggregationDefinition;
use Spiral\Tests\ODM\Fixtures\BadAggregates;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\GoodAggregates;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class AggregateSchemaTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    /**
     * @expectedException \Spiral\ODM\Exceptions\SchemaException
     * @expectedExceptionMessage Aggregation Spiral\Tests\ODM\Fixtures\BadAggregates.'pieces'
     *                           refers to undefined document 'Spiral\Tests\ODM\Fixtures\DataPiece'
     */
    public function testBadAggregation()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(BadAggregates::class));

        $builder->packSchema();
    }

    /**
     * @expectedException \Spiral\ODM\Exceptions\SchemaException
     * @expectedExceptionMessage Aggregation Spiral\Tests\ODM\Fixtures\BadAggregates.'pieces'
     *                           refers to non storable document
     *                           'Spiral\Tests\ODM\Fixtures\DataPiece'
     */
    public function testAggregationToEmbedded()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(BadAggregates::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $builder->packSchema();
    }

    public function testGoodAggregation()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($aggregates = $this->makeSchema(GoodAggregates::class));
        $builder->addSchema($this->makeSchema(User::class));

        $this->assertCount(2, $aggregates->getAggregations());

        $this->assertEquals([
            'user'  => new AggregationDefinition(Document::ONE, User::class,
                ['_id' => 'self::userId']),
            'users' => new AggregationDefinition(Document::MANY, User::class, []),
        ], $aggregates->getAggregations());

        $aggregation = $aggregates->getAggregations()['user'];

        $this->assertSame(Document::ONE, $aggregation->getType());
        $this->assertSame(User::class, $aggregation->getClass());
        $this->assertSame(['_id' => 'self::userId'], $aggregation->getQuery());
    }
}