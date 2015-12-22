<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Encrypter;

use Spiral\Core\Component;
use Spiral\Core\Container\InjectableInterface;
use Spiral\Encrypter\Exceptions\DecryptException;
use Spiral\Encrypter\Exceptions\EncrypterException;

/**
 * Default implementation of spiral encrypter.
 * 
 * @todo found some references to old mcrypt, to remove them
 */
class Encrypter extends Component implements EncrypterInterface, InjectableInterface
{
    /**
     * Injection is dedicated to outer class since Encrypter is pretty simple.
     */
    const INJECTOR = EncrypterManager::class;

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
     * One of the openssl cipher values, or the name of the algorithm as string.
     *
     * @var string
     */
    private $cipher = 'aes-256-cbc';

    /**
     * Encrypter constructor.
     *
     * @param string $key
     * @param string $cipher
     */
    public function __construct($key, $cipher = 'aes-256-cbc')
    {
        $this->setKey($key);

        if (!empty($cipher)) {
            //We are allowing to skip definition of cipher to be used
            $this->cipher = $cipher;
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
     * 
     * Data encoded using json_encode method, only supported formats are allowed!
     */
    public function encrypt($data)
    {
        if (empty($this->key)) {
            throw new EncrypterException("Encryption key should not be empty.");
        }

        $vector = $this->createIV(openssl_cipher_iv_length($this->cipher));

        try{
                $serialized = json_encode($data);
        } catch (\ErrorException $e){
            throw new EncrypterException("Unsupported data format", null, $e);
        }
        
        $encrypted = openssl_encrypt(
            $serialized,
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
     * 
     * json_decode with assoc flag set to true
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

            return json_decode($decrypted, true);
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
        //todo: double check if this is good idea
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
