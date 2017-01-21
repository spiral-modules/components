<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Migrations;

use Spiral\Migrations\Atomizer;
use Spiral\Migrations\Migration;
use Spiral\Reactor\ClassDeclaration;
use Spiral\Reactor\FileDeclaration;

abstract class AtomizerTest extends BaseTest
{
    public function testCreateAndDiff()
    {
        //Create thought migration
        $this->migrator->configure();

        $schema = $this->schema('sample');
        $schema->primary('id');
        $schema->integer('value');
        $schema->index(['value']);

        $this->atomize('migration1', [$schema]);
        $this->migrator->run();
        $this->assertTrue($this->db->hasTable('sample'));

        $this->migrator->rollback();
        $this->assertFalse($this->db->hasTable('sample'));
    }

    public function testCreateAndThenUpdate()
    {
        //Create thought migration
        $this->migrator->configure();

        $schema = $this->schema('sample');
        $schema->primary('id');
        $schema->integer('value');
        $schema->index(['value']);
        $this->atomize('migration1', [$schema]);

        $this->migrator->run();
        $this->assertSame('integer', $this->schema('sample')->column('value')->abstractType());

        $schema = $this->schema('sample');
        $schema->float('value');
        $this->atomize('migration2', [$schema]);

        $this->migrator->run();
        $this->assertSame('float', $this->schema('sample')->column('value')->abstractType());
        $this->assertTrue($this->db->hasTable('sample'));

        $this->migrator->rollback();
        $this->assertSame('integer', $this->schema('sample')->column('value')->abstractType());
        $this->assertTrue($this->db->hasTable('sample'));

        $this->migrator->rollback();
        $this->assertFalse($this->db->hasTable('sample'));
    }

    public function testCreateAndThenUpdateAddDefault()
    {
        //Create thought migration
        $this->migrator->configure();

        $schema = $this->schema('sample');
        $schema->primary('id');
        $schema->integer('value');
        $schema->index(['value']);
        $this->atomize('migration1', [$schema]);

        $this->migrator->run();
        $this->assertSame('integer', $this->schema('sample')->column('value')->abstractType());

        $schema = $this->schema('sample');
        $schema->float('value')->defaultValue(2);

        $this->atomize('migration2', [$schema]);

        $this->migrator->run();
        $this->assertSame('float', $this->schema('sample')->column('value')->abstractType());
        $this->assertEquals(2, $this->schema('sample')->column('value')->getDefaultValue());

        $this->assertTrue($this->db->hasTable('sample'));

        $this->migrator->rollback();
        $this->assertSame('integer', $this->schema('sample')->column('value')->abstractType());
        $this->assertSame(null, $this->schema('sample')->column('value')->getDefaultValue());

        $this->assertTrue($this->db->hasTable('sample'));

        $this->migrator->rollback();
        $this->assertFalse($this->db->hasTable('sample'));
    }

    public function testCreateAndThenUpdateEnumDefault()
    {
        //Create thought migration
        $this->migrator->configure();

        $schema = $this->schema('sample');
        $schema->primary('id');
        $schema->enum('value', ['a', 'b']);
        $schema->index(['value']);
        $this->atomize('migration1', [$schema]);

        $this->migrator->run();
        $this->assertSame(['a', 'b'], $this->schema('sample')->column('value')->getEnumValues());

        $schema = $this->schema('sample');
        $schema->enum('value', ['a', 'b', 'c']);
        $schema->index(['value'])->unique(true);
        $this->atomize('migration2', [$schema]);

        $this->migrator->run();
        $this->assertSame(
            ['a', 'b', 'c'],
            $this->schema('sample')->column('value')->getEnumValues()
        );

        $this->assertTrue($this->db->hasTable('sample'));

        $this->migrator->rollback();
        $this->assertSame(['a', 'b'], $this->schema('sample')->column('value')->getEnumValues());

        $this->assertTrue($this->db->hasTable('sample'));

        $this->migrator->rollback();
        $this->assertFalse($this->db->hasTable('sample'));
    }

    public function testChangeColumnScale()
    {
        //Create thought migration
        $this->migrator->configure();

        $schema = $this->schema('sample');
        $schema->primary('id');
        $schema->decimal('value', 1, 2);
        $this->atomize('migration1', [$schema]);

        $this->migrator->run();
        $this->assertSame(1, $this->schema('sample')->column('value')->getPrecision());
        $this->assertSame(2, $this->schema('sample')->column('value')->getScale());

        $schema = $this->schema('sample');
        $schema->decimal('value', 2, 3);
        $this->atomize('migration2', [$schema]);

        $this->migrator->run();

        $this->assertSame(2, $this->schema('sample')->column('value')->getPrecision());
        $this->assertSame(3, $this->schema('sample')->column('value')->getScale());

        $this->assertTrue($this->db->hasTable('sample'));

        $this->migrator->rollback();
        $this->assertSame(1, $this->schema('sample')->column('value')->getPrecision());
        $this->assertSame(2, $this->schema('sample')->column('value')->getScale());

        $this->assertTrue($this->db->hasTable('sample'));

        $this->migrator->rollback();
        $this->assertFalse($this->db->hasTable('sample'));
    }

    protected function atomize(string $name, array $tables)
    {
        $atomizer = new Atomizer(
            new Atomizer\MigrationRenderer(new Atomizer\AliasLookup($this->dbal))
        );

        foreach ($tables as $table) {
            $atomizer->addTable($table);
        }

        //Rendering
        $declaration = new ClassDeclaration($name, Migration::class);

        $declaration->method('up')->setPublic();
        $declaration->method('down')->setPublic();

        $atomizer->declareChanges($declaration->method('up')->source());
        $atomizer->revertChanges($declaration->method('down')->source());

        $file = new FileDeclaration();
        $file->addElement($declaration);

        $this->repository->registerMigration($name, $name, $file);
    }
}