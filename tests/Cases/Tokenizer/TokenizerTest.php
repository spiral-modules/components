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
use Spiral\Core\Components\Loader;
use Spiral\Core\Configurator;
use Spiral\Files\FileManager;
use Spiral\Tests\Cases\Tokenizer\Classes\ClassA;
use Spiral\Tests\Cases\Tokenizer\Classes\ClassB;
use Spiral\Tests\Cases\Tokenizer\Classes\ClassC;
use Spiral\Tests\Cases\Tokenizer\Classes\Inner\ClassD;
use Spiral\Tests\RuntimeMemory;
use Spiral\Tests\TestCase;
use Spiral\Tokenizer\Reflections\ReflectionArgument;
use Spiral\Tokenizer\Tokenizer;

class TokenizerTest extends TestCase
{
    /**
     * @var Loader
     */
    protected $loader = null;

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

        $functionUsages = $reflection->getCalls();

        $functionA = null;
        $functionB = null;

        foreach ($functionUsages as $usage) {
            if ($usage->getName() == 'test_function_a') {
                $functionA = $usage;
            }

            if ($usage->getName() == 'test_function_b') {
                $functionB = $usage;
            }
        }

        $this->assertNotEmpty($functionA);
        $this->assertNotEmpty($functionB);

        $this->assertSame(2, count($functionA->getArguments()));
        $this->assertSame(ReflectionArgument::VARIABLE, $functionA->argument(0)->getType());
        $this->assertSame('$this', $functionA->argument(0)->getValue());

        $this->assertSame(ReflectionArgument::EXPRESSION, $functionA->argument(1)->getType());
        $this->assertSame('$a+$b', $functionA->argument(1)->getValue());

        $this->assertSame(2, count($functionB->getArguments()));

        $this->assertSame(ReflectionArgument::STRING, $functionB->argument(0)->getType());
        $this->assertSame('"string"', $functionB->argument(0)->getValue());
        $this->assertSame('string', $functionB->argument(0)->stringValue());

        $this->assertSame(ReflectionArgument::CONSTANT, $functionB->argument(1)->getType());
        $this->assertSame('123', $functionB->argument(1)->getValue());

        if (false) {
            $a = $b = null;
            test_function_a($this, $a + $b);
            test_function_b("string", 123);
        }
    }

    protected function setUp()
    {
        $this->loader = new Loader(new RuntimeMemory());
    }

    protected function tearDown()
    {
        $this->loader->disable();
        $this->loader = null;
    }

    /**
     * @param array $config
     * @return Tokenizer
     */
    protected function tokenizer(array $config = [])
    {
        if (empty($config)) {
            $config = [
                'directories' => [__DIR__],
                'exclude'     => ['XX']
            ];
        }

        $tokenizer = new Tokenizer(
            new Configurator($config),
            new RuntimeMemory(),
            new FileManager(),
            $this->loader);

        $tokenizer->setLogger(new NullLogger());

        return $tokenizer;
    }
}

trait TestTrait
{

}

interface TestInterface
{

}