<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Encrypter;

use Defuse\Crypto\Key;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Encrypter\Configs\EncrypterConfig;

/**
 * Only manages encrypter injections (factory).
 */
class EncrypterManager implements InjectorInterface, SingletonInterface
{
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
     * Generate new random encryption key (binary format).
     *
     * @return string
     */
    public function generateKey(): string
    {
        return Key::createNewRandomKey()->saveToAsciiSafeString();
    }

    /**
     * {@inheritdoc}
     */
    public function createInjection(\ReflectionClass $class, $context = null)
    {
        return $class->newInstance(base64_decode($this->config->getKey()));
    }
}