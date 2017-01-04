<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas;

use Interop\Container\ContainerInterface;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ORM\Configs\MutatorsConfig;
use Spiral\ORM\Entities\RecordSource;
use Spiral\ORM\Record;
use Spiral\Tokenizer\ClassesInterface;

/**
 * Provides ability to automatically locate schemas in a project. Can be user redefined in order to
 * automatically include custom classes.
 */
class SchemaLocator
{
    /**
     * Container is used for lazy resolution for ClassesInterface.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Locate all available document schemas in a project.
     *
     * @return SchemaInterface[]
     */
    public function locateSchemas(): array
    {
        if (!$this->container->has(ClassesInterface::class)) {
            return [];
        }

        /**
         * @var ClassesInterface $classes
         */
        $classes = $this->container->get(ClassesInterface::class);

        $schemas = [];
        foreach ($classes->getClasses(Record::class) as $class) {
            if ($class['abstract']) {
                continue;
            }

            $schemas[] = new RecordSchema(
                new ReflectionEntity($class['name']),
                $this->container->get(MutatorsConfig::class)
            );
        }

        return $schemas;
    }

    /**
     * Locate all DocumentSources defined by user. Must return values in a form of
     * Document::class => Source::class.
     *
     * @return array
     */
    public function locateSources(): array
    {
        if (!$this->container->has(ClassesInterface::class)) {
            return [];
        }

        /**
         * @var ClassesInterface $classes
         */
        $classes = $this->container->get(ClassesInterface::class);

        $result = [];
        foreach ($classes->getClasses(RecordSource::class) as $class) {
            $source = $class['name'];
            if ($class['abstract'] || empty($source::RECORD)) {
                continue;
            }

            $result[$source::RECORD] = $source;
        }

        return $result;
    }
}