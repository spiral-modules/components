<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Encrypter;

use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Encrypter\Config\EncrypterConfig;

/**
 * Only manages encrypter injections.
 */
class EncrypterManager implements InjectorInterface, SingletonInterface
{
    /**
     * To be constructed only once.
     */
    const SINGLETON = self::class;

    /**
     * @var EncrypterConfig
     */
    protected $config = null;

    /**
     * @param EncrypterConfig $config
     */
    public function __construct(EncrypterConfig $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        return $class->newInstance($this->config->getKey(), $this->config->getCipher());
    }
}