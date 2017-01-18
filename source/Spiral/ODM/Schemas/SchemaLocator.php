<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Schemas;

use Interop\Container\ContainerInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Models\Reflections\ReflectionEntity;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Entities\DocumentSource;
use Spiral\Tokenizer\ClassesInterface;

/**
 * Provides ability to automatically locate schemas in a project. Can be user redefined in order to
 * automatically include custom classes.
 *
 * This is lazy implementation.
 */
class SchemaLocator implements LocatorInterface
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
     * {@inheritdoc}
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
        foreach ($classes->getClasses(DocumentEntity::class) as $class) {
            if ($class['abstract']) {
                continue;
            }

            $schemas[] = $this->container->get(FactoryInterface::class)->make(
                DocumentSchema::class,
                ['reflection' => new ReflectionEntity($class['name']),]
            );
        }

        return $schemas;
    }


    /**
     * {@inheritdoc}
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
        foreach ($classes->getClasses(DocumentSource::class) as $class) {
            $source = $class['name'];
            if ($class['abstract'] || empty($source::DOCUMENT)) {
                continue;
            }

            $result[$source::DOCUMENT] = $source;
        }

        return $result;
    }
}