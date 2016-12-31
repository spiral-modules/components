<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Stempler;

use Spiral\Stempler\Stempler;
use Spiral\Stempler\StemplerLoader;
use Spiral\Stempler\Syntaxes\DarkSyntax;

class StemplerTest extends \PHPUnit_Framework_TestCase
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
     * Render view and return it's blank lines.
     *
     * @param string $view
     *
     * @return array
     */
    protected function render($view)
    {
        $stempler = new Stempler(
            new StemplerLoader([
                'default'   => [__DIR__ . '/fixtures/default/'],
                'namespace' => [__DIR__ . '/fixtures/namespace/',]
            ]),
            new DarkSyntax()
        );

        $lines = explode("\n", self::normalizeEndings($stempler->compile($view)));
        return array_values(array_map('trim', array_filter($lines, 'trim')));
    }

    /**
     * Normalize string endings to avoid EOL problem. Replace \n\r and multiply new lines with
     * single \n.
     *
     * @param string $string       String to be normalized.
     * @param bool   $joinMultiple Join multiple new lines into one.
     *
     * @return string
     */
    public static function normalizeEndings(string $string, bool $joinMultiple = true): string
    {
        if (!$joinMultiple) {
            return str_replace("\r\n", "\n", $string);
        }

        return preg_replace('/[\n\r]+/', "\n", $string);
    }
}