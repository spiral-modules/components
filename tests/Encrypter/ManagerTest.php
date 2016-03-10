<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Encrypter;

use Spiral\Core\Container;
use Spiral\Encrypter\Configs\EncrypterConfig;
use Spiral\Encrypter\Encrypter;
use Spiral\Encrypter\EncrypterInterface;

class ManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testInjection()
    {
        $key = \Crypto::CreateNewRandomKey();

        $container = new Container();
        $container->bind(EncrypterInterface::class, Encrypter::class);

        //Manager must be created automatically
        $container->bind(
            EncrypterConfig::class,
            new EncrypterConfig(['key' => base64_encode($key)])
        );

        $this->assertInstanceOf(
            EncrypterInterface::class,
            $container->get(EncrypterInterface::class)
        );

        $this->assertInstanceOf(Encrypter::class, $container->get(EncrypterInterface::class));

        $encrypter = $container->get(EncrypterInterface::class);
        $this->assertSame($key, $encrypter->getKey());
    }
}