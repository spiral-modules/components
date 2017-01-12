<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM;

use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\Tests\ORM\Fixtures\Post;
use Spiral\Tests\ORM\Fixtures\Tag;
use Spiral\Tests\ORM\Fixtures\User;
use Spiral\Tests\ORM\Traits\ORMTrait;

class SchemasTest extends \PHPUnit_Framework_TestCase
{
    use ORMTrait;

    public function testBasic()
    {
        $builder = $this->makeBuilder();

        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Post::class));
        $builder->addSchema($this->makeSchema(Tag::class));


        $this->assertTrue($builder->hasSchema(User::class));
        $this->assertTrue($builder->hasSchema(Post::class));
        $this->assertTrue($builder->hasSchema(Tag::class));

        $this->assertSame(User::class, $builder->getSchema(User::class)->getClass());
        $this->assertSame(Post::class, $builder->getSchema(Post::class)->getClass());
        $this->assertSame(Tag::class, $builder->getSchema(Tag::class)->getClass());
    }

    public function testRender()
    {
        $manager = $this->databaseManager();
        $builder = $this->makeBuilder($manager);

        $builder->addSchema($this->makeSchema(User::class));
        $builder->addSchema($this->makeSchema(Post::class));
        $builder->addSchema($this->makeSchema(Tag::class));

        $builder->renderSchema();

        $tables = $builder->getTables();

        foreach ($tables as $table) {
            $this->assertInstanceOf(AbstractTable::class, $table);
            $this->assertFalse($table->exists());
        }

        //Storing in database
        $builder->pushSchema();

        foreach ($tables as $table) {
            $this->assertTrue($manager->database()->hasTable($table->getName()));
        }
    }
}