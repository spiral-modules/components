<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\Stempler;

class CompileTest extends BaseTest
{
    public function testBaseA()
    {
        $result = $this->compile('base-a');

        $this->assertSame('Block A defined in file A(default).', $result[0]);
        $this->assertSame('Block B defined in file A(default).', $result[1]);
        $this->assertSame('Block C defined in file A(default).', $result[2]);
    }

    public function testBaseB()
    {
        $result = $this->compile('base-b');

        $this->assertSame('Block A defined in file B(default).', $result[0]);
        $this->assertSame('Block B defined in file A(default).', $result[1]);
        $this->assertSame('Block C defined in file A(default).', $result[2]);
    }

    public function testBaseC()
    {
        $result = $this->compile('namespace:base-e');

        $this->assertSame('Block A defined in file B(default).', $result[0]);
        $this->assertSame('Block B defined in file A(default).', $result[1]);
        $this->assertSame('Block B defined in file D(namespace). Base E.', $result[2]);
        $this->assertSame('Block C defined in file C(default).', $result[3]);
    }
}