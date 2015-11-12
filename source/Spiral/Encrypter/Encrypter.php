<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Encrypter;

use Spiral\Core\Component;
use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Encrypter\Exceptions\DecryptException;
use Spiral\Encrypter\Exceptions\EncrypterException;

/**
 * Default implementation of spiral encrypter.
 */
class Encrypter extends Component implements EncrypterInterface, SingletonInterface
{
    /**
     * To edit configuration in runtime.
     */
    use ConfigurableTrait;

    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'encrypter';

    /**
     * Keys to use in packed data. This is internal constants.
     */
    const IV        = 'a';
    const DATA      = 'b';
    const SIGNATURE = 'c';

    /**
     * @var string
     */
    private $key = '';

    /**
     * One of the MCRYPT_CIPERNAME constants, or the name of the algorithm as string.
     *
     * @var string
     */
    private $cipher = 'aes-256-cbc';

    /**
     * @param ConfiguratorInterface $configurator
     */
    public function __construct(ConfiguratorInterface $configurator)
    {
        $this->config = $configurator->getConfig(static::CONFIG);

        $this->setKey($this->config['key']);

        if (!empty($this->config['cipher'])) {
            //We are allowing to skip definition of ciper to be used
            $this->cipher = $this->config['cipher'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setKey($key)
    {
        $this->key = (string)$key;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Change encryption method. One of MCRYPT_CIPERNAME constants.
     *
     * @param  string $cipher
     * @return $this
     */
    public function setCipher($cipher)
    {
        $this->cipher = $cipher;

        return $this;
    }

    /**
     * @return string
     */
    public function getCipher()
    {
        return $this->cipher;
    }

    /**
     * Restore default encrypter key and method.
     *
     * @return $this
     * @throws EncrypterException
     */
    public function restoreDefaults()
    {
        $this->setKey($this->config['key']);
        $this->setCipher($this->config['cipher']);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $passWeak Do not throw an exception if result is "weak". Not recommended.
     */
    public function random($length, $passWeak = false)
    {
        if ($length < 1) {
            throw new EncrypterException("Random string length should be at least 1 byte long.");
        }

        if (!$result = openssl_random_pseudo_bytes($length, $cryptoStrong)) {
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
     */
    public function encrypt($data)
    {
        if (empty($this->key)) {
            throw new EncrypterException("Encryption key should not be empty.");
        }

        $vector = $this->createIV(openssl_cipher_iv_length($this->cipher));

        $encrypted = openssl_encrypt(
            serialize($data),
            $this->cipher,
            $this->key,
            false,
            $vector
        );

        $result = json_encode([
            self::IV        => ($vector = bin2hex($vector)),
            self::DATA      => $encrypted,
            self::SIGNATURE => $this->sign($encrypted, $vector)
        ]);

        return base64_encode($result);
    }

    /**
     * {@inheritdoc}
     */
    public function decrypt($payload)
    {
        try {
            $payload = json_decode(base64_decode($payload), true);

            if (empty($payload) || !is_array($payload)) {
                throw new DecryptException("Invalid dataset.");
            }

            assert(!empty($payload[self::IV]));
            assert(!empty($payload[self::DATA]));
            assert(!empty($payload[self::SIGNATURE]));
        } catch (\ErrorException $exception) {
            throw new DecryptException("Unable to unpack provided data.");
        }

        //Verifying signature
        if ($payload[self::SIGNATURE] !== $this->sign($payload[self::DATA], $payload[self::IV])) {
            throw new DecryptException("Encrypted data does not have valid signature.");
        }

        try {
            $decrypted = openssl_decrypt(
                base64_decode($payload[self::DATA]),
                $this->cipher,
                $this->key,
                true,
                hex2bin($payload[self::IV])
            );

            return unserialize($decrypted);
        } catch (\ErrorException $exception) {
            throw new DecryptException($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * Sign string using private key.
     *
     * @param string $string
     * @param string $salt
     * @return string
     */
    public function sign($string, $salt = null)
    {
        return hash_hmac('sha256', $string . ($salt ? ':' . $salt : ''), $this->key);
    }

    /**
     * Create an initialization vector (IV) from a random source with specified size.
     *
     * @link http://php.net/manual/en/function.mcrypt-create-iv.php
     * @param int $length
     * @return string
     */
    private function createIV($length = 16)
    {
        return $length ? $this->random($length, false) : '';
    }
}