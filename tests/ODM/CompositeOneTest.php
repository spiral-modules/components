<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Schemas\Definitions\CompositionDefinition;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\NullableComposition;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class CompositeOneTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testCompositeOne()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($user = $this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        //No data piece been registered
        $this->assertEquals([
            'piece' => new CompositionDefinition(DocumentEntity::ONE, DataPiece::class)
        ], $user->getCompositions($builder));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class);

        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(DataPiece::class, $user->piece);
    }

    public function testNotNullable()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, ['piece' => ['value' => 'abc']]);

        $this->assertSame('abc', $user->piece->value);
    }

    public function testCompositeOneWithValue()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, ['piece' => ['value' => 'abc']]);

        $this->assertSame('abc', $user->piece->value);
    }

    public function testCompositeOneSetValue()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, ['piece' => ['value' => 'abc']]);

        $this->assertSame('abc', $user->piece->value);

        //Must pass value to piece
        $user->piece = ['value' => 'new-value'];

        $this->assertInstanceOf(DataPiece::class, $user->piece);
        $this->assertSame('new-value', $user->piece->value);
    }

    public function testCompositeOneSetValueThoughtSetFields()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, ['piece' => ['value' => 'abc']]);
        $this->assertSame('abc', $user->piece->value);

        //Must pass value to piece
        $user->setFields(['piece' => ['value' => 'new-value']]);

        $this->assertInstanceOf(DataPiece::class, $user->piece);
        $this->assertSame('new-value', $user->piece->value);
    }

    public function testCompositeOneSetValueWithTypecasting()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $user = $odm->instantiate(User::class, ['piece' => ['value' => 'abc']]);
        $this->assertSame('abc', $user->piece->value);

        //Must pass value to piece
        $user->piece = ['value' => 123];

        $this->assertInstanceOf(DataPiece::class, $user->piece);
        $this->assertSame('123', $user->piece->value);
    }

    public function testNullableCompositionsCustomDefault()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(NullableComposition::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $model = $odm->instantiate(NullableComposition::class, ['piece' => ['value' => 'abc']]);
        $this->assertSame('abc', $model->piece->value);

        //Must pass value to piece
        $model->piece = ['value' => 123];

        $this->assertInstanceOf(DataPiece::class, $model->piece);
        $this->assertSame('123', $model->piece->value);
    }

    public function testNullableCompositionsNullDefault()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(NullableComposition::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $model = $odm->instantiate(NullableComposition::class);
        $this->assertNull($model->piece);
    }

    public function testNullableCompositionsNullToNoNull()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(NullableComposition::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $model = $odm->instantiate(NullableComposition::class);
        $this->assertNull($model->piece);

        $model->piece = ['value' => 'abc'];
        $this->assertInstanceOf(DataPiece::class, $model->piece);
        $this->assertSame('abc', $model->piece->value);
    }

    public function testNullableCompositionsNotNullToNull()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(NullableComposition::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $model = $odm->instantiate(NullableComposition::class, ['piece' => ['value' => 'abc']]);

        $this->assertInstanceOf(DataPiece::class, $model->piece);
        $this->assertSame('abc', $model->piece->value);

        $model->piece = null;
        $this->assertNull($model->piece);
    }

    public function setComposite()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($this->makeSchema(NullableComposition::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        $odm = $this->makeODM();
        $odm->setSchema($builder);

        $model = $odm->instantiate(NullableComposition::class, ['piece' => ['value' => 'abc']]);

        $this->assertInstanceOf(DataPiece::class, $model->piece);
        $this->assertSame('abc', $model->piece->value);

        $model->piece = $odm->instantiate(DataPiece::class, ['value' => 'another-value']);

        $this->assertInstanceOf(DataPiece::class, $model->piece);
        $this->assertSame('another-value', $model->piece->value);
    }
}