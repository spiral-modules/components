<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Storage\Servers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\SimpleCache\CacheInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Exceptions\ServerException;

/**
 * Provides abstraction level to work with data located in Rackspace cloud.
 */
class RackspaceServer extends AbstractServer implements LoggerAwareInterface
{
    use LoggerTrait;

    /**
     * @var string
     */
    private $authToken = [];

    /**
     * Some operations can be performed only inside one region.
     *
     * @var array
     */
    private $regions = [];

    /**
     * @var array
     */
    protected $options = [
        'server'     => 'https://auth.api.rackspacecloud.com/v1.0',
        'authServer' => 'https://identity.api.rackspacecloud.com/v2.0/tokens',
        'username'   => '',
        'apiKey'     => '',
        'cache'      => true,
        'lifetime'   => 86400
    ];

    /**
     * Cache store to remember connection.
     *
     * @invisible
     * @var CacheInterface
     */
    protected $cache = null;

    /**
     * @var ClientInterface
     */
    protected $client = null;

    /**
     * @param array                $options
     * @param FilesInterface       $files
     * @param CacheInterface|null  $cache
     * @param ClientInterface|null $client
     */
    public function __construct(
        array $options,
        FilesInterface $files = null,
        CacheInterface $cache = null,
        ClientInterface $client = null
    ) {
        parent::__construct($options, $files);
        $this->cache = $cache;

        if (!empty($this->cache) && $this->options['cache']) {
            $this->authToken = $this->cache->get(
                $this->options['username'] . '@rackspace-token'
            );

            $this->regions = (array)$this->cache->get(
                $this->options['username'] . '@rackspace-regions'
            );
        }

        //Initiating Guzzle
        $this->setClient($client ?? new Client($this->options))->connect();
    }

    /**
     * @param ClientInterface $client
     *
     * @return self
     */
    public function setClient(ClientInterface $client): RackspaceServer
    {
        $this->client = $client;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param ResponseInterface $response Reference.
     *
     * @return bool
     */
    public function exists(
        BucketInterface $bucket,
        string $name,
        ResponseInterface &$response = null
    ): bool {
        try {
            $response = $this->client->send($this->buildRequest('HEAD', $bucket, $name));
        } catch (ClientException $e) {
            if ($e->getCode() == 404) {
                return false;
            }

            if ($e->getCode() == 401) {
                $this->reconnect();

                return $this->exists($bucket, $name);
            }

            //Some unexpected error
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function size(BucketInterface $bucket, string $name)
    {
        if (!$this->exists($bucket, $name, $response)) {
            return false;
        }

        /**
         * @var ResponseInterface $response
         */
        return (int)$response->getHeaderLine('Content-Length');
    }

    /**
     * {@inheritdoc}
     */
    public function put(BucketInterface $bucket, string $name, $source): bool
    {
        if (empty($mimetype = \GuzzleHttp\Psr7\mimetype_from_filename($name))) {
            $mimetype = self::DEFAULT_MIMETYPE;
        }

        try {
            $request = $this->buildRequest('PUT', $bucket, $name, [
                'Content-Type' => $mimetype,
                'Etag'         => md5_file($this->castFilename($source))
            ]);

            $this->client->send($request->withBody($this->castStream($source)));
        } catch (ClientException $e) {
            if ($e->getCode() == 401) {
                $this->reconnect();

                return $this->put($bucket, $name, $source);
            }

            //Some unexpected error
            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function allocateStream(BucketInterface $bucket, string $name): StreamInterface
    {
        try {
            $response = $this->client->send($this->buildRequest('GET', $bucket, $name));
        } catch (ClientException $e) {
            if ($e->getCode() == 401) {
                $this->reconnect();

                return $this->allocateStream($bucket, $name);
            }

            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        return $response->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(BucketInterface $bucket, string $name)
    {
        try {
            $this->client->send($this->buildRequest('DELETE', $bucket, $name));
        } catch (ClientException $e) {
            if ($e->getCode() == 401) {
                $this->reconnect();
                $this->delete($bucket, $name);
            } elseif ($e->getCode() != 404) {
                throw new ServerException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename(BucketInterface $bucket, string $oldName, string $newName): bool
    {
        try {
            $request = $this->buildRequest('PUT', $bucket, $newName, [
                'X-Copy-From'    => '/' . $bucket->getOption('container') . '/' . rawurlencode($oldName),
                'Content-Length' => 0
            ]);

            $this->client->send($request);
        } catch (ClientException $e) {
            if ($e->getCode() == 401) {
                $this->reconnect();

                return $this->rename($bucket, $oldName, $newName);
            }

            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        //Deleting old file
        $this->delete($bucket, $oldName);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function copy(BucketInterface $bucket, BucketInterface $destination, string $name): bool
    {
        if ($bucket->getOption('region') != $destination->getOption('region')) {
            $this->logger()->warning(
                "Copying between regions are not allowed by Rackspace and performed using local buffer."
            );

            //Using local memory/disk as buffer
            return parent::copy($bucket, $destination, $name);
        }

        try {
            $request = $this->buildRequest('PUT', $destination, $name, [
                'X-Copy-From'    => '/' . $bucket->getOPtion('container') . '/' . rawurlencode($name),
                'Content-Length' => 0
            ]);

            $this->client->send($request);
        } catch (ClientException $e) {
            if ($e->getCode() == 401) {
                $this->reconnect();

                return $this->copy($bucket, $destination, $name);
            }

            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * Connect to rackspace servers using new or cached token.
     *
     * @throws ServerException
     */
    protected function connect()
    {
        if (!empty($this->authToken)) {
            //Already got credentials from cache
            return;
        }

        //Credentials request
        $request = new Request(
            'POST',
            $this->options['authServer'],
            ['Content-Type' => 'application/json'],
            json_encode([
                'auth' => [
                    'RAX-KSKEY:apiKeyCredentials' => [
                        'username' => $this->options['username'],
                        'apiKey'   => $this->options['apiKey']
                    ]
                ]
            ])
        );

        try {
            /**
             * @var ResponseInterface $response
             */
            $response = $this->client->send($request);
        } catch (ClientException $e) {
            if ($e->getCode() == 401) {
                throw new ServerException(
                    "Unable to perform RackSpace authorization using given credentials"
                );
            }

            throw new ServerException($e->getMessage(), $e->getCode(), $e);
        }

        $response = json_decode((string)$response->getBody(), 1);
        foreach ($response['access']['serviceCatalog'] as $location) {
            if ($location['name'] == 'cloudFiles') {
                foreach ($location['endpoints'] as $server) {
                    $this->regions[$server['region']] = $server['publicURL'];
                }
            }
        }

        if (!isset($response['access']['token']['id'])) {
            throw new ServerException("Unable to fetch rackspace auth token");
        }

        $this->authToken = $response['access']['token']['id'];

        if (!empty($this->cache) && $this->options['cache']) {
            $this->cache->set(
                $this->options['username'] . '@rackspace-token',
                $this->authToken,
                $this->options['lifetime']
            );

            $this->cache->set(
                $this->options['username'] . '@rackspace-regions',
                $this->regions,
                $this->options['lifetime']
            );
        }
    }

    /**
     * Reconnect.
     *
     * @throws ServerException
     */
    protected function reconnect()
    {
        $this->authToken = null;
        $this->connect();
    }

    /**
     * Create instance of UriInterface based on provided bucket options and storage object name.
     *
     * @param BucketInterface $bucket
     * @param string          $name
     *
     * @return UriInterface
     * @throws ServerException
     */
    protected function buildUri(BucketInterface $bucket, string $name): UriInterface
    {
        if (empty($bucket->getOption('region'))) {
            throw new ServerException("Every RackSpace container should have specified region");
        }

        $region = $bucket->getOption('region');
        if (!isset($this->regions[$region])) {
            throw new ServerException("'{$region}' region is not supported by RackSpace");
        }

        return new Uri(
            $this->regions[$region] . '/' . $bucket->getOption('container') . '/' . rawurlencode($name)
        );
    }

    /**
     * Create pre-configured object request.
     *
     * @param string          $method
     * @param BucketInterface $bucket
     * @param string          $name
     * @param array           $headers
     *
     * @return RequestInterface
     */
    protected function buildRequest(
        string $method,
        BucketInterface $bucket,
        string $name,
        array $headers = []
    ): RequestInterface {
        //Request with added auth headers
        return new Request(
            $method,
            $this->buildUri($bucket, $name),
            $headers + [
                'X-Auth-Token' => $this->authToken,
                'Date'         => gmdate('D, d M Y H:i:s T')
            ]
        );
    }
}