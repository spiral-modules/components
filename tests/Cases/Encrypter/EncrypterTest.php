<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tests\Cases\Encrypter;

use Spiral\Core\Configurator;
use Spiral\Encrypter\Encrypter;

class EncryptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param array $config
     * @return Encrypter
     */
    protected function encrypter($config = ['key' => '1234567890123456'])
    {
        return new Encrypter(new Configurator($config));
    }

    public function testEncryption()
    {
        $encrypter = $this->encrypter();

        $encrypted = $encrypter->encrypt('test string');
        $this->assertNotEquals('test string', $encrypted);
        $this->assertEquals('test string', $encrypter->decrypt($encrypted));

        $encrypter->setKey('0987654321123456');
        $encrypted = $encrypter->encrypt('test string');
        $this->assertNotEquals('test string', $encrypted);
        $this->assertEquals('test string', $encrypter->decrypt($encrypted));

        $encrypter->setMethod('aes-128-cbc');
        $encrypted = $encrypter->encrypt('test string');
        $this->assertNotEquals('test string', $encrypted);
        $this->assertEquals('test string', $encrypter->decrypt($encrypted));
    }

    /**
     * @expectedException \Spiral\Encrypter\DecryptionException
     * @expectedExceptionMessage Invalid dataset.
     */
    public function testBadData()
    {
        $encrypter = $this->encrypter();

        $encrypted = $encrypter->encrypt('test string');
        $this->assertNotEquals('test string', $encrypted);
        $this->assertEquals('test string', $encrypter->decrypt($encrypted));
        $encrypter->decrypt('badData.' . $encrypted);
    }

    /**
     * @expectedException \Spiral\Encrypter\DecryptionException
     * @expectedExceptionMessage Encrypted data does not have valid signature.
     */
    public function testBadSignature()
    {
        $encrypter = $this->encrypter();

        $encrypted = $encrypter->encrypt('test string');
        $this->assertNotEquals('test string', $encrypted);
        $this->assertEquals('test string', $encrypter->decrypt($encrypted));

        $encrypted = base64_decode($encrypted);

        $encrypted = json_decode($encrypted, true);
        $encrypted[Encrypter::SIGNATURE] = 'BADONE';

        $encrypted = base64_encode(json_encode($encrypted));
        $encrypter->decrypt($encrypted);
    }
}