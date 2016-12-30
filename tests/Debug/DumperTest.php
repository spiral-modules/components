<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Debug;

use Spiral\Debug\Dumper;

//No much need to validate output, just check that it's not dying
class DumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDumpIntoBuffer()
    {
        $dumper = new Dumper();

        ob_start();
        $dumper->dump(1);
        $result = ob_get_clean();

        $this->assertSame($dumper->dump(1, Dumper::OUTPUT_RETURN), $result);
    }

    public function testDumpScalar()
    {
        $dumper = new Dumper();

        $dump = $dumper->dump(123, Dumper::OUTPUT_RETURN);

        $this->assertContains('123', $dump);
    }

    public function testDumpScalarString()
    {
        $dumper = new Dumper();

        $dump = $dumper->dump('test-string', Dumper::OUTPUT_RETURN);

        $this->assertContains('test-string', $dump);
    }

    public function testDumpScalarStringEscaped()
    {
        $dumper = new Dumper();

        $dump = $dumper->dump('test<>string', Dumper::OUTPUT_RETURN);

        $this->assertContains('test&lt;&gt;string', $dump);
    }

    public function testDumpArray()
    {
        $dumper = new Dumper();

        $dump = $dumper->dump(['G', 'B', 'N'], Dumper::OUTPUT_RETURN);

        $this->assertContains('array', $dump);
        $this->assertContains('G', $dump);
        $this->assertContains('B', $dump);
        $this->assertContains('N', $dump);
    }

    protected $_value_ = 'test value';

    /**
     * @invisible
     * @var string
     */
    protected $_invisible_ = 'invisible value';

    public function dumpObject()
    {
        $dumper = new Dumper();

        $dump = $dumper->dump($this, Dumper::OUTPUT_RETURN);

        $this->assertContains(self::class, $dump);
        $this->assertContains('invisible value', $dump);
        $this->assertContains('_value_', $dump);

        $this->assertNotContains('test value', $dump);
        $this->assertNotContains('_invisible_', $dump);
    }

    public function dumpObjectOtherStyle()
    {
        $dumper = new Dumper(10, new Dumper\InversedStyle());

        $dump = $dumper->dump($this, Dumper::OUTPUT_RETURN);

        $this->assertContains(self::class, $dump);
        $this->assertContains('invisible value', $dump);
        $this->assertContains('_value_', $dump);

        $this->assertNotContains('test value', $dump);
        $this->assertNotContains('_invisible_', $dump);
    }
}