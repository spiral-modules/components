<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Stempler;

class ImportsTest extends BaseTest
{
    public function testImportNone()
    {
        $result = $this->render('import-a');

        $this->assertSame('Base file.', $result[0]);
        $this->assertSame('<tag name="test">value</tag>', $result[1]);
    }

    public function testImportAsAlias()
    {
        $result = $this->render('import-b');

        $this->assertSame('Base file.', $result[0]);
        $this->assertSame('<tag class="tag-b" name="test">value</tag>', $result[1]);
    }

    public function testImportWithPrefix()
    {
        $result = $this->render('import-c');

        $this->assertSame('Base file.', $result[0]);
        $this->assertSame('<tag name="test">value</tag>', $result[1]);
        $this->assertSame('<tag class="tag-b" name="test">value</tag>', $result[2]);
    }


    public function testImportBundle()
    {
        $result = $this->render('import-bundle');

        $this->assertSame('<tag name="1" id="1">inner-1</tag>', $result[0]);
        $this->assertSame('<tag class="tag-b" name="2">inner-2</tag>', $result[1]);
    }

    /**
     * @expectedException \Spiral\Stempler\Exceptions\StemplerException
     * @expectedExceptionMessage Unable to locate view 'includes/tag-c.php' in namespace 'default'
     */
    public function testImportWithPrefixErrorTag()
    {
        $result = $this->render('import-d');

        $this->assertSame('Base file.', $result[0]);
        $this->assertSame('<tag class="tag-b" name="test">value</tag>', $result[1]);
    }

    /**
     * @expectedException \Spiral\Stempler\Exceptions\SyntaxException
     * @expectedExceptionMessage Undefined use element
     */
    public function testInvalidUseElement()
    {
        $result = $this->render('import-e');

        $this->assertSame('Base file.', $result[0]);
        $this->assertSame('<tag class="tag-b" name="test">value</tag>', $result[1]);
    }
}