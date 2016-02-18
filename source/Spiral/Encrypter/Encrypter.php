<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Encrypter;

use Spiral\Core\Container\InjectableInterface;
use Spiral\Encrypter\Exceptions\DecryptException;
use Spiral\Encrypter\Exceptions\EncrypterException;
use Spiral\Encrypter\Exceptions\EncryptException;

/**
 * Default implementation of spiral encrypter. Sugary implementation at top of defuse/php-encryption
 *
 * @todo move to 2.x when ready
 * @see https://github.com/defuse/php-encryption
 */
class Encrypter implements EncrypterInterface, InjectableInterface
{
    /**
     * Injection is dedicated to outer class since Encrypter is pretty simple.
     */
    const INJECTOR = EncrypterManager::class;

    /**
     * @var string
     */
    private $key = '';

    /**
     * Encrypter constructor.
     *
     * @param string $key
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * {@inheritdoc}
     */
    public function withKey($key)
    {
        $encrypter = clone $this;
        $encrypter->key = (string)$key;

        return $encrypter;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     *
     * @todo double check
     * @param bool $passWeak Do not throw an exception if result is "weak". Not recommended.
     */
    public function random($length, $passWeak = false)
    {
        if ($length < 1) {
            throw new EncrypterException("Random string length should be at least 1 byte long.");
        }

        $result = openssl_random_pseudo_bytes($length, $cryptoStrong);
        if ($result === false) {
            throw new EncrypterException(
                "Unable to generate pseudo-random string with {$length} length."
            );
        }

        if (!$passWeak && !(bool)$cryptoStrong) {
            throw new EncrypterException("Weak random result received.");
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * Data encoded using json_encode method, only supported formats are allowed!
     */
    public function encrypt($data)
    {
        $packed = json_encode($data);

        try {
            return \Crypto::Encrypt($packed, $this->key);
        } catch (\CannotPerformOperationException $e) {
            throw new EncryptException($e->getMessage(), $e->getCode(), $e);
        } catch (\CryptoTestFailedException $e) {
            throw new EncrypterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * json_decode with assoc flag set to true
     */
    public function decrypt($payload)
    {
        try {
            $result = \Crypto::Decrypt($payload, $this->key);

            return json_decode($result, true);
        } catch (\InvalidCiphertextException $e) {
            throw new DecryptException($e->getMessage(), $e->getCode(), $e);
        } catch (\CannotPerformOperationException $e) {
            throw new DecryptException($e->getMessage(), $e->getCode(), $e);
        } catch (\CryptoTestFailedException $e) {
            throw new EncrypterException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
