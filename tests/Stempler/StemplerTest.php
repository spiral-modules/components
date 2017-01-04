<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Stempler;

class StemplerTest extends BaseTest
{
    public function testBaseA()
    {
        $result = $this->render('base-a');

        $this->assertSame('Block A defined in file A(default).', $result[0]);
        $this->assertSame('Block B defined in file A(default).', $result[1]);
        $this->assertSame('Block C defined in file A(default).', $result[2]);
    }

    public function testBaseB()
    {
        $result = $this->render('base-b');

        $this->assertSame('Block A defined in file B(default).', $result[0]);
        $this->assertSame('Block B defined in file A(default).', $result[1]);
        $this->assertSame('Block C defined in file A(default).', $result[2]);
    }

    public function testBaseC()
    {
        $result = $this->render('namespace:base-e');

        $this->assertSame('Block A defined in file B(default).', $result[0]);
        $this->assertSame('Block B defined in file A(default).', $result[1]);
        $this->assertSame('Block B defined in file D(namespace). Base E.', $result[2]);
        $this->assertSame('Block C defined in file C(default).', $result[3]);
    }

    public function testIncludesA()
    {
        $result = $this->render('includes-a');

        $this->assertSame('Include A, block A.', $result[0]);
        $this->assertSame('<tag name="tag-a">', $result[1]);
        $this->assertSame('Include A, block B (inside tag).', $result[2]);
        $this->assertSame('</tag>', $result[3]);
        $this->assertSame('Include A, block C.', $result[4]);
    }

    public function testIncludesB()
    {
        $result = $this->render('includes-b');

        $this->assertSame('Include A, block A.', $result[0]);
        $this->assertSame('<tag name="tag-a">', $result[1]);
        $this->assertSame('<tag class="tag-b" name="tag-b">', $result[2]);
        $this->assertSame('Include A, block C (inside tag B).', $result[3]);
        $this->assertSame('</tag>', $result[4]);
        $this->assertSame('</tag>', $result[5]);
        $this->assertSame('Include A, block C.', $result[6]);
    }

    public function testIncludesC()
    {
        $result = $this->render('includes-c');

        $this->assertSame('Include A, block A.', $result[0]);
        $this->assertSame('<tag name="tag-a">', $result[1]);
        $this->assertSame('Include A, block B (inside tag).', $result[2]);
        $this->assertSame('</tag>', $result[3]);
        $this->assertSame('<tag class="tag-b" name="ABC">', $result[4]);
        $this->assertSame('<tag name="tag-a">', $result[5]);
        $this->assertSame('Include A, block B (inside tag).', $result[6]);
        $this->assertSame('</tag>', $result[7]);
        $this->assertSame('</tag>', $result[8]);
    }

    public function testIncludesD()
    {
        $result = $this->render('namespace:includes-d');

        $this->assertSame('<tag class="class my-class" id="123">', $result[0]);
        $this->assertSame('<tag class="tag-b" name="tag-b">', $result[1]);
        $this->assertSame('<tag class="class new-class" value="abc">', $result[2]);
        $this->assertSame('Some context.', $result[3]);
        $this->assertSame('</tag>', $result[4]);
        $this->assertSame('</tag>', $result[5]);
        $this->assertSame('</tag>', $result[6]);
    }

    /**
     * @expectedException \Spiral\Stempler\Exceptions\StemplerException
     * @expectedExceptionMessage Unable to locate view 'includes-none.php' in namespace 'default'
     */
    public function testInvalidParent()
    {
        $result = $this->render('includes-e');
    }
}