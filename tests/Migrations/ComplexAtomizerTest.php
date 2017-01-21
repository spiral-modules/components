<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Migrations;

abstract class ComplexAtomizerTest extends BaseTest
{
    public function testCreateMultiple()
    {
        //Create thought migration
        $this->migrator->configure();

        $schema = $this->schema('sample');
        $schema->primary('id');
        $schema->integer('value');
        $schema->index(['value']);

        $schema1 = $this->schema('sample1');
        $schema1->primary('id');
        $schema1->float('value');
        $schema1->integer('sample_id');
        $schema1->foreign('sample_id')->references('sample', 'id');

        $this->atomize('migration1', [$schema, $schema1]);
        $this->migrator->run();

        $this->assertTrue($this->db->hasTable('sample'));
        $this->assertTrue($this->db->hasTable('sample1'));

        $this->migrator->rollback();
        $this->assertFalse($this->db->hasTable('sample'));
        $this->assertFalse($this->db->hasTable('sample1'));
    }

    public function testCreateMultipleWithPivot()
    {
        //Create thought migration
        $this->migrator->configure();

        $schema = $this->schema('sample');
        $schema->primary('id');
        $schema->integer('value');
        $schema->index(['value']);

        $schema1 = $this->schema('sample1');
        $schema1->primary('id');
        $schema1->float('value');
        $schema1->integer('sample_id');
        $schema1->foreign('sample_id')->references('sample', 'id');

        $schema2 = $this->schema('sample2');
        $schema2->integer('sample_id');
        $schema2->foreign('sample_id')->references('sample', 'id');
        $schema2->integer('sample1_id');
        $schema2->foreign('sample1_id')->references('sample1', 'id');

        $this->atomize('migration1', [$schema, $schema1, $schema2]);
        $this->migrator->run();

        $this->assertTrue($this->db->hasTable('sample'));
        $this->assertTrue($this->db->hasTable('sample1'));
        $this->assertTrue($this->db->hasTable('sample2'));

        $this->migrator->rollback();
        $this->assertFalse($this->db->hasTable('sample'));
        $this->assertFalse($this->db->hasTable('sample1'));
        $this->assertFalse($this->db->hasTable('sample2'));
    }
}