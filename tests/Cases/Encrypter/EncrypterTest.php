<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Cases\Encrypter;

use Spiral\Encrypter\Encrypter;

class EncryptionTest extends \PHPUnit_Framework_TestCase
{
    public function testImmutable()
    {
        $encrypter = new Encrypter($keyA = \Crypto::CreateNewRandomKey());
        $new = $encrypter->withKey($keyB = \Crypto::CreateNewRandomKey());

        $this->assertNotSame($encrypter, $new);

        $this->assertEquals($keyA, $encrypter->getKey());
        $this->assertEquals($keyB, $new->getKey());

    }

    public function testEncryption()
    {
        $encrypter = new Encrypter(\Crypto::CreateNewRandomKey());

        $encrypted = $encrypter->encrypt('test string');
        $this->assertNotEquals('test string', $encrypted);
        $this->assertEquals('test string', $encrypter->decrypt($encrypted));

        $encrypter = $encrypter->withKey(\Crypto::CreateNewRandomKey());

        $encrypted = $encrypter->encrypt('test string');
        $this->assertNotEquals('test string', $encrypted);
        $this->assertEquals('test string', $encrypter->decrypt($encrypted));

        $encrypted = $encrypter->encrypt('test string');
        $this->assertNotEquals('test string', $encrypted);
        $this->assertEquals('test string', $encrypter->decrypt($encrypted));
    }

    /**
     * @expectedException \Spiral\Encrypter\Exceptions\DecryptException
     */
    public function testBadData()
    {
        $encrypter = new Encrypter(\Crypto::CreateNewRandomKey());

        $encrypted = $encrypter->encrypt('test string');
        $this->assertNotEquals('test string', $encrypted);
        $this->assertEquals('test string', $encrypter->decrypt($encrypted));

        $encrypter->decrypt('badData.' . $encrypted);
    }
}