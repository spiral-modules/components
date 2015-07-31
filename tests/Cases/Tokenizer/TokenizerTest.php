<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tests\Cases\Tokenizer;

use Psr\Log\NullLogger;
use Spiral\Core\Configurator;
use Spiral\Core\Loader;
use Spiral\Files\FileManager;
use Spiral\Tests\Cases\Tokenizer\Classes\ClassA;
use Spiral\Tests\Cases\Tokenizer\Classes\ClassB;
use Spiral\Tests\Cases\Tokenizer\Classes\ClassC;
use Spiral\Tests\Cases\Tokenizer\Classes\Inner\ClassD;
use Spiral\Tests\RuntimeHippocampus;
use Spiral\Tokenizer\Reflections\FunctionUsage\Argument;
use Spiral\Tokenizer\Tokenizer;

class TokenizerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Loader
     */
    protected $loader = null;

    /**
     * Configured Tokenizer component.
     *
     * @param array $config
     * @return Tokenizer
     */
    protected function tokenizer(array $config = [])
    {
        if (empty($config))
        {
            $config = [
                'directories' => [__DIR__],
                'exclude'     => ['XX']
            ];
        }

        $tokenizer = new Tokenizer(
            new Configurator($config),
            new RuntimeHippocampus(),
            new FileManager(),
            $this->loader
        );

        $tokenizer->setLogger(new NullLogger());

        return $tokenizer;
    }

    protected function setUp()
    {
        $this->loader = new Loader(new RuntimeHippocampus());
    }

    protected function tearDown()
    {
        $this->loader->disable();
        $this->loader = null;
    }

    public function testClassesAll()
    {
        $tokenizer = $this->tokenizer();

        //Direct loading
        $classes = $tokenizer->getClasses();

        $this->assertArrayHasKey(self::class, $classes);
        $this->assertArrayHasKey(ClassA::class, $classes);
        $this->assertArrayHasKey(ClassB::class, $classes);
        $this->assertArrayHasKey(ClassC::class, $classes);
        $this->assertArrayHasKey(ClassD::class, $classes);

        //Excluded
        $this->assertArrayNotHasKey('Spiral\Tests\Cases\Tokenizer\Classes\ClassXX', $classes);
        $this->assertArrayNotHasKey('Spiral\Tests\Cases\Tokenizer\Classes\Bad_Class', $classes);
    }

    public function testClassesByNamespace()
    {
        $tokenizer = $this->tokenizer();

        //By namespace
        $classes = $tokenizer->getClasses(null, 'Spiral\Tests\Cases\Tokenizer\Classes\Inner');

        $this->assertArrayHasKey(ClassD::class, $classes);

        $this->assertArrayNotHasKey(self::class, $classes);
        $this->assertArrayNotHasKey(ClassA::class, $classes);
        $this->assertArrayNotHasKey(ClassB::class, $classes);
        $this->assertArrayNotHasKey(ClassC::class, $classes);
    }

    public function testClassesByInterface()
    {
        $tokenizer = $this->tokenizer();

        //By interface
        $classes = $tokenizer->getClasses('Spiral\Tests\Cases\Tokenizer\TestInterface');

        $this->assertArrayHasKey(ClassB::class, $classes);
        $this->assertArrayHasKey(ClassC::class, $classes);

        $this->assertArrayNotHasKey(self::class, $classes);
        $this->assertArrayNotHasKey(ClassA::class, $classes);
        $this->assertArrayNotHasKey(ClassD::class, $classes);
    }

    public function testClassesByTrait()
    {
        $tokenizer = $this->tokenizer();

        //By trait
        $classes = $tokenizer->getClasses('Spiral\Tests\Cases\Tokenizer\TestTrait');

        $this->assertArrayHasKey(ClassB::class, $classes);
        $this->assertArrayHasKey(ClassC::class, $classes);

        $this->assertArrayNotHasKey(self::class, $classes);
        $this->assertArrayNotHasKey(ClassA::class, $classes);
        $this->assertArrayNotHasKey(ClassD::class, $classes);
    }

    public function testClassesByClassA()
    {
        $tokenizer = $this->tokenizer();

        //By class
        $classes = $tokenizer->getClasses(ClassA::class);

        $this->assertArrayHasKey(ClassA::class, $classes);
        $this->assertArrayHasKey(ClassB::class, $classes);
        $this->assertArrayHasKey(ClassC::class, $classes);
        $this->assertArrayHasKey(ClassD::class, $classes);

        $this->assertArrayNotHasKey(self::class, $classes);
    }

    public function testClassesByClassB()
    {
        $tokenizer = $this->tokenizer();

        //By class
        $classes = $tokenizer->getClasses(ClassB::class);

        $this->assertArrayHasKey(ClassB::class, $classes);
        $this->assertArrayHasKey(ClassC::class, $classes);

        $this->assertArrayNotHasKey(self::class, $classes);
        $this->assertArrayNotHasKey(ClassA::class, $classes);
        $this->assertArrayNotHasKey(ClassD::class, $classes);
    }

    public function testFileReflection()
    {
        $reflection = $this->tokenizer()->fileReflection(__FILE__);

        $this->assertContains(self::class, $reflection->getClasses());

        $functionUsages = $reflection->getFunctionUsages();

        $functionA = null;
        $functionB = null;

        foreach ($functionUsages as $usage)
        {
            if ($usage->getFunction() == 'test_function_a')
            {
                $functionA = $usage;
            }

            if ($usage->getFunction() == 'test_function_b')
            {
                $functionB = $usage;
            }
        }

        $this->assertNotEmpty($functionA);
        $this->assertNotEmpty($functionB);

        $this->assertSame(2, count($functionA->getArguments()));
        $this->assertSame(Argument::VARIABLE, $functionA->getArgument(0)->getType());
        $this->assertSame('$this', $functionA->getArgument(0)->getValue());

        $this->assertSame(Argument::EXPRESSION, $functionA->getArgument(1)->getType());
        $this->assertSame('$a+$b', $functionA->getArgument(1)->getValue());

        $this->assertSame(2, count($functionB->getArguments()));

        $this->assertSame(Argument::STRING, $functionB->getArgument(0)->getType());
        $this->assertSame('"string"', $functionB->getArgument(0)->getValue());
        $this->assertSame('string', $functionB->getArgument(0)->stringValue());

        $this->assertSame(Argument::CONSTANT, $functionB->getArgument(1)->getType());
        $this->assertSame('123', $functionB->getArgument(1)->getValue());

        if (false)
        {
            $a = $b = null;
            test_function_a($this, $a + $b);
            test_function_b("string", 123);
        }
    }
}

trait TestTrait
{

}

interface TestInterface
{

}