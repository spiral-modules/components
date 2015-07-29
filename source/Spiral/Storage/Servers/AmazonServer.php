<?php
/**
 * Spiral Framework, SpiralScout LLC.
 *
 * @package   spiralFramework
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2011
 */
namespace Spiral\Components\Storage\Servers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\StorageServer;

class AmazonServer extends StorageServer
{
    /**
     * Server configuration, connection options, auth keys and certificates.
     *
     * @var array
     */
    protected $options = [
        'server'    => 'https://s3.amazonaws.com',
        'timeout'   => 0,
        'accessKey' => '',
        'secretKey' => ''
    ];

    /**
     * Guzzle client.
     *
     * @var Client
     */
    protected $client = null;

    /**
     * Every server represent one virtual storage which can be either local, remote or cloud based.
     * Every server should support basic set of low-level operations (create, move, copy and etc).
     *
     * @param FilesInterface $files   File component.
     * @param array          $options Storage connection options.
     */
    public function __construct(FilesInterface $files, array $options)
    {
        parent::__construct($files, $options);

        //Some options can be passed directly for guzzle client
        $this->client = new Client($this->options);
    }

    /**
     * Check if given object (name) exists in specified container. Method should never fail if file
     * not exists and will return bool in any condition.
     *
     * @param BucketInterface $bucket Bucket instance associated with specific server.
     * @param string          $name   Storage object name.
     * @return bool|ResponseInterface
     */
    public function exists(BucketInterface $bucket, $name)
    {
        try
        {
            $response = $this->client->send($this->buildRequest('HEAD', $bucket, $name));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() == 404)
            {
                return false;
            }

            //Something wrong with connection
            throw $exception;
        }

        if ($response->getStatusCode() !== 200)
        {
            return false;
        }

        return $response;
    }


    /**
     * Retrieve object size in bytes, should return false if object does not exists.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return int|bool
     */
    public function size(BucketInterface $bucket, $name)
    {
        if (empty($response = $this->exists($bucket, $name)))
        {
            return false;
        }

        return (int)$response->getHeaderLine('Content-Length');
    }

    /**
     * Upload storage object using given filename or stream. Method can return false in case of failed
     * upload or thrown custom exception if needed.
     *
     * @param BucketInterface        $bucket Bucket instance.
     * @param string                 $name   Given storage object name.
     * @param string|StreamInterface $origin Local filename or stream to use for creation.
     * @return bool
     */
    public function put(BucketInterface $bucket, $name, $origin)
    {
        if (empty($mimetype = \GuzzleHttp\Psr7\mimetype_from_filename($name)))
        {
            $mimetype = self::DEFAULT_MIMETYPE;
        }

        $request = $this->buildRequest(
            'PUT',
            $bucket,
            $name,
            [
                'Content-MD5'  => base64_encode(md5_file($this->castFilename($origin), true)),
                'Content-Type' => $mimetype
            ],
            [
                'Acl'          => $bucket->getOption('public') ? 'public-read' : 'private',
                'Content-Type' => $mimetype
            ]
        );

        return $this->client->send(
            $request->withBody($this->castStream($origin))
        )->getStatusCode() == 200;
    }

    /**
     * Get temporary read-only stream used to represent remote content. This method is very similar
     * to localFilename, however in some cases it may store data content in memory.
     *
     * Method should return false or thrown an exception if stream can not be allocated.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return StreamInterface|false
     */
    public function allocateStream(BucketInterface $bucket, $name)
    {
        try
        {
            $response = $this->client->send($this->buildRequest('GET', $bucket, $name));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() != 404)
            {
                //Some authorization or other error
                throw $exception;
            }

            return false;
        }

        return $response->getBody();
    }


    /**
     * Delete storage object from specified container. Method should not fail if object does not
     * exists.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     */
    public function delete(BucketInterface $bucket, $name)
    {
        $this->client->send($this->buildRequest('DELETE', $bucket, $name));
    }

    /**
     * Rename storage object without changing it's container. This operation does not require
     * object recreation or download and can be performed on remote server.
     *
     * Method should return false or thrown an exception if object can not be renamed.
     *
     * @param BucketInterface $bucket  Bucket instance.
     * @param string          $oldname Storage object name.
     * @param string          $newname New storage object name.
     * @return bool
     */
    public function rename(BucketInterface $bucket, $oldname, $newname)
    {
        try
        {
            $this->client->send($this->buildRequest(
                'PUT',
                $bucket,
                $newname,
                [],
                [
                    'Acl'         => $bucket->getOption('public') ? 'public-read' : 'private',
                    'Copy-Source' => $this->buildUri($bucket, $oldname)->getPath()
                ]
            ));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() != 404)
            {
                //Some authorization or other error
                throw $exception;
            }

            return false;
        }

        $this->delete($bucket, $oldname);

        return true;
    }


    /**
     * Copy object to another internal (under same server) container, this operation may not
     * require file download and can be performed remotely.
     *
     * Method should return false or thrown an exception if object can not be copied.
     *
     * @param BucketInterface $bucket      Bucket instance.
     * @param BucketInterface $destination Destination bucket (under same server).
     * @param string          $name        Storage object name.
     * @return bool
     */
    public function copy(BucketInterface $bucket, BucketInterface $destination, $name)
    {
        try
        {
            $this->client->send($this->buildRequest(
                'PUT',
                $destination,
                $name,
                [],
                [
                    'Acl'         => $destination->getOption('public') ? 'public-read' : 'private',
                    'Copy-Source' => $this->buildUri($bucket, $name)->getPath()
                ]
            ));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() != 404)
            {
                //Some authorization or other error
                throw $exception;
            }

            return false;
        }

        return true;
    }

    /**
     * Create instance of UriInterface based on provided container instance and storage object name.
     *
     * @param BucketInterface $container Bucket instance.
     * @param string          $name      Storage object name.
     * @return UriInterface
     */
    protected function buildUri(BucketInterface $container, $name)
    {
        return new Uri(
            $this->options['server'] . '/' . $container->getOption('bucket') . '/' . rawurlencode($name)
        );
    }

    /**
     * Helper method used to create signed amazon request with correct set of headers.
     *
     * @param string          $method   Http method.
     * @param BucketInterface $bucket   Bucket instance.
     * @param string          $name     Storage object name.
     * @param array           $headers  Request headers.
     * @param array           $commands Amazon commands associated with values.
     * @return RequestInterface
     */
    protected function buildRequest(
        $method,
        BucketInterface $bucket,
        $name,
        array $headers = [],
        array $commands = []
    )
    {
        $headers += [
            'Date'         => gmdate('D, d M Y H:i:s T'),
            'Content-MD5'  => '',
            'Content-Type' => ''
        ];

        $packedCommands = $this->packCommands($commands);

        return $this->signRequest(
            new Request($method, $this->buildUri($bucket, $name), $headers + $packedCommands),
            $packedCommands
        );
    }

    /**
     * Generate request headers based on provided set of amazon commands.
     *
     * @param array $commands
     * @return array
     */
    protected function packCommands(array $commands)
    {
        $headers = [];
        foreach ($commands as $command => $value)
        {
            $headers['X-Amz-' . $command] = $value;
        }

        return $headers;
    }

    /**
     * Sign amazon request.
     *
     * @param RequestInterface $request
     * @param array            $packedCommands Headers generated based on request commands, see
     *                                         packCommands() method for more information.
     * @return RequestInterface
     */
    protected function signRequest(RequestInterface $request, array $packedCommands = [])
    {
        $signature = [
            $request->getMethod(),
            $request->getHeaderLine('Content-MD5'),
            $request->getHeaderLine('Content-Type'),
            $request->getHeaderLine('Date')
        ];

        $normalizedCommands = [];
        foreach ($packedCommands as $command => $value)
        {
            if (!empty($value))
            {
                $normalizedCommands[] = strtolower($command) . ':' . $value;
            }
        }

        if ($normalizedCommands)
        {
            sort($normalizedCommands);
            $signature[] = join("\n", $normalizedCommands);
        }

        $signature[] = $request->getUri()->getPath();

        return $request->withAddedHeader(
            'Authorization',
            'AWS ' . $this->options['accessKey'] . ':' . base64_encode(
                hash_hmac('sha1', join("\n", $signature), $this->options['secretKey'], true)
            )
        );
    }
} 