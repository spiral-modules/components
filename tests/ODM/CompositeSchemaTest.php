<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Mockery as m;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Schemas\Definitions\CompositionDefinition;
use Spiral\Tests\ODM\Fixtures\Admin;
use Spiral\Tests\ODM\Fixtures\DataPiece;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class CompositeSchemaTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testCompositionsWithNoClass()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($user = $this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));

        //No data piece been registered
        $this->assertEquals([], $user->getCompositions($builder));
        $this->assertEquals([], $admin->getCompositions($builder));
    }

    public function testCompositions()
    {
        $builder = $this->makeBuilder();
        $builder->addSchema($user = $this->makeSchema(User::class));
        $builder->addSchema($admin = $this->makeSchema(Admin::class));
        $builder->addSchema($this->makeSchema(DataPiece::class));

        //No data piece been registered
        $this->assertEquals([
            'piece' => new CompositionDefinition(DocumentEntity::ONE, DataPiece::class)
        ], $user->getCompositions($builder));
        $this->assertEquals([
            'piece'  => new CompositionDefinition(DocumentEntity::ONE, DataPiece::class),
            'pieces' => new CompositionDefinition(DocumentEntity::MANY, DataPiece::class)
        ], $admin->getCompositions($builder));
    }
}