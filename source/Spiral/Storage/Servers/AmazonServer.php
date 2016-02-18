<?php
/**
 * Spiral Framework, SpiralScout LLC.
 *
 * @package   spiralFramework
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2011
 */
namespace Spiral\Storage\Servers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Exceptions\ServerException;
use Spiral\Storage\StorageServer;

/**
 * Provides abstraction level to work with data located in Amazon S3 cloud.
 */
class AmazonServer extends StorageServer
{
    /**
     * @var array
     */
    protected $options = [
        'server'    => 'https://s3.amazonaws.com',
        'timeout'   => 0,
        'accessKey' => '',
        'secretKey' => ''
    ];

    /**
     * @todo DI in constructor
     * @var ClientInterface
     */
    protected $client = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(FilesInterface $files, array $options)
    {
        parent::__construct($files, $options);

        //This code is going to use additional abstraction layer to connect storage and guzzle
        $this->client = new Client($this->options);
    }

    /**
     * @param ClientInterface $client
     * @return $this
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool|ResponseInterface
     */
    public function exists(BucketInterface $bucket, $name)
    {
        try {
            $response = $this->client->send($this->buildRequest('HEAD', $bucket, $name));
        } catch (ClientException $exception) {
            if ($exception->getCode() == 404) {
                return false;
            }

            //Something wrong with connection
            throw $exception;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function size(BucketInterface $bucket, $name)
    {
        if (empty($response = $this->exists($bucket, $name))) {
            return false;
        }

        return (int)$response->getHeaderLine('Content-Length');
    }

    /**
     * {@inheritdoc}
     */
    public function put(BucketInterface $bucket, $name, $source)
    {
        if (empty($mimetype = \GuzzleHttp\Psr7\mimetype_from_filename($name))) {
            $mimetype = self::DEFAULT_MIMETYPE;
        }

        $request = $this->buildRequest(
            'PUT',
            $bucket,
            $name,
            $this->createHeaders($bucket, $name, $source),
            [
                'Acl'          => $bucket->getOption('public') ? 'public-read' : 'private',
                'Content-Type' => $mimetype
            ]
        );

        $response = $this->client->send($request->withBody($this->castStream($source)));
        if ($response->getStatusCode() != 200) {
            throw new ServerException("Unable to put '{$name}' to Amazon server.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function allocateStream(BucketInterface $bucket, $name)
    {
        try {
            $response = $this->client->send($this->buildRequest('GET', $bucket, $name));
        } catch (ClientException $exception) {
            if ($exception->getCode() != 404) {
                //Some authorization or other error
                throw $exception;
            }

            throw new ServerException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $response->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(BucketInterface $bucket, $name)
    {
        $this->client->send($this->buildRequest('DELETE', $bucket, $name));
    }

    /**
     * {@inheritdoc}
     */
    public function rename(BucketInterface $bucket, $oldname, $newname)
    {
        try {
            $request = $this->buildRequest('PUT', $bucket, $newname, [], [
                'Acl'         => $bucket->getOption('public') ? 'public-read' : 'private',
                'Copy-Source' => $this->buildUri($bucket, $oldname)->getPath()
            ]);

            $this->client->send($request);
        } catch (ClientException $exception) {
            if ($exception->getCode() != 404) {
                //Some authorization or other error
                throw $exception;
            }

            throw new ServerException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $this->delete($bucket, $oldname);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function copy(BucketInterface $bucket, BucketInterface $destination, $name)
    {
        try {
            $request = $this->buildRequest('PUT', $destination, $name, [], [
                'Acl'         => $destination->getOption('public') ? 'public-read' : 'private',
                'Copy-Source' => $this->buildUri($bucket, $name)->getPath()
            ]);

            $this->client->send($request);
        } catch (ClientException $exception) {
            if ($exception->getCode() != 404) {
                //Some authorization or other error
                throw $exception;
            }

            throw new ServerException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return true;
    }

    /**
     * Create instance of UriInterface based on provided bucket options and storage object name.
     *
     * @param BucketInterface $bucket
     * @param string          $name
     * @return UriInterface
     */
    protected function buildUri(BucketInterface $bucket, $name)
    {
        return new Uri(
            $this->options['server'] . '/' . $bucket->getOption('bucket') . '/' . rawurlencode($name)
        );
    }

    /**
     * Helper to create configured PSR7 request with set of amazon commands.
     *
     * @param string          $method
     * @param BucketInterface $bucket
     * @param string          $name
     * @param array           $headers
     * @param array           $commands Amazon commands associated with values.
     * @return RequestInterface
     */
    protected function buildRequest(
        $method,
        BucketInterface $bucket,
        $name,
        array $headers = [],
        array $commands = []
    ) {
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
    private function packCommands(array $commands)
    {
        $headers = [];
        foreach ($commands as $command => $value) {
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
    private function signRequest(RequestInterface $request, array $packedCommands = [])
    {
        $signature = [
            $request->getMethod(),
            $request->getHeaderLine('Content-MD5'),
            $request->getHeaderLine('Content-Type'),
            $request->getHeaderLine('Date')
        ];

        $normalizedCommands = [];
        foreach ($packedCommands as $command => $value) {
            if (!empty($value)) {
                $normalizedCommands[] = strtolower($command) . ':' . $value;
            }
        }

        if ($normalizedCommands) {
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

    /**
     * Generate object headers.
     *
     * @param BucketInterface $bucket
     * @param string          $name
     * @param mixed           $source
     * @return array
     */
    private function createHeaders(BucketInterface $bucket, $name, $source)
    {
        if (empty($mimetype = \GuzzleHttp\Psr7\mimetype_from_filename($name))) {
            $mimetype = self::DEFAULT_MIMETYPE;
        };

        $headers = $bucket->getOption('headers', []);

        if (!empty($maxAge = $bucket->getOption('maxAge', 0))) {
            //Shortcut
            $headers['Cache-control'] = 'max-age=' . $bucket->getOption('maxAge', 0) . ', public';
            $headers['Expires'] = gmdate(
                'D, d M Y H:i:s T',
                time() + $bucket->getOption('maxAge', 0)
            );
        }

        return $headers + [
            'Content-MD5'  => base64_encode(md5_file($this->castFilename($source), true)),
            'Content-Type' => $mimetype
        ];
    }
} 