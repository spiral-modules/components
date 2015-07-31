<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
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
use Psr\Log\LoggerAwareInterface;
use Spiral\Cache\StoreInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Exceptions\ServerException;
use Spiral\Storage\StorageServer;

/**
 * Provides abstraction level to work with data located in Rackspace cloud.
 */
class RackspaceServer extends StorageServer implements LoggerAwareInterface
{
    /**
     * There is few warning messages.
     */
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
     * @var StoreInterface
     */
    protected $store = null;

    /**
     * @var Client
     */
    protected $client = null;

    /**
     * @param FilesInterface $files
     * @param StoreInterface $store
     * @param array          $options
     */
    public function __construct(FilesInterface $files, StoreInterface $store, array $options)
    {
        parent::__construct($files, $options);
        $this->store = $store;

        if ($this->options['cache'])
        {
            $this->authToken = $this->store->get($this->options['username'] . '@rackspace-token');
            $this->regions = $this->store->get($this->options['username'] . '@rackspace-regions');
        }

        //Some options can be passed directly for guzzle client
        $this->client = new Client($this->options);
        $this->connect();
    }

    /**
     * {@inheritdoc}
     *
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

            if ($exception->getCode() == 401)
            {
                $this->reconnect();

                return $this->exists($bucket, $name);
            }

            //Some unexpected error
            throw new ServerException($exception->getMessage(), $exception->getCode(), $exception);
        }

        if ($response->getStatusCode() !== 200)
        {
            return false;
        }

        return $response;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function put(BucketInterface $bucket, $name, $source)
    {
        if (empty($mimetype = \GuzzleHttp\Psr7\mimetype_from_filename($name)))
        {
            $mimetype = self::DEFAULT_MIMETYPE;
        }

        try
        {
            $request = $this->buildRequest(
                'PUT',
                $bucket,
                $name,
                [
                    'Content-Type' => $mimetype,
                    'Etag'         => md5_file($this->castFilename($source))
                ]
            );

            $this->client->send($request->withBody($this->castStream($source)));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() == 401)
            {
                $this->reconnect();

                return $this->put($bucket, $name, $source);
            }

            //Some unexpected error
            throw new ServerException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function allocateStream(BucketInterface $bucket, $name)
    {
        try
        {
            $response = $this->client->send($this->buildRequest('GET', $bucket, $name));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() == 401)
            {
                $this->reconnect();

                return $this->allocateStream($bucket, $name);
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
        try
        {
            $this->client->send($this->buildRequest('DELETE', $bucket, $name));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() == 401)
            {
                $this->reconnect();
                $this->delete($bucket, $name);
            }
            elseif ($exception->getCode() != 404)
            {
                throw new ServerException($exception->getMessage(), $exception->getCode(), $exception);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename(BucketInterface $bucket, $oldname, $newname)
    {
        try
        {
            $this->client->send($this->buildRequest(
                'PUT',
                $bucket,
                $newname,
                [
                    'X-Copy-From'    => '/' . $bucket->getOption('container') . '/' . rawurlencode($oldname),
                    'Content-Length' => 0
                ]
            ));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() == 401)
            {
                $this->reconnect();

                return $this->rename($bucket, $oldname, $newname);
            }

            throw new ServerException($exception->getMessage(), $exception->getCode(), $exception);
        }

        //Deleting old file
        $this->delete($bucket, $oldname);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function copy(BucketInterface $bucket, BucketInterface $destination, $name)
    {
        if ($bucket->getOption('region') != $destination->getOption('region'))
        {
            $this->logger()->warning(
                "Copying between regions are not allowed by Rackspace and performed using local buffer."
            );

            //Using local memory/disk as buffer
            return parent::copy($bucket, $destination, $name);
        }

        try
        {
            $this->client->send($this->buildRequest(
                'PUT',
                $destination,
                $name,
                [
                    'X-Copy-From'    => '/' . $bucket->getOPtion('container') . '/' . rawurlencode($name),
                    'Content-Length' => 0
                ]
            ));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() == 401)
            {
                $this->reconnect();

                return $this->copy($bucket, $destination, $name);
            }

            throw new ServerException($exception->getMessage(), $exception->getCode(), $exception);
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
        if (!empty($this->authToken))
        {
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

        try
        {
            /**
             * @var ResponseInterface $response
             */
            $response = $this->client->send($request);
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() == 401)
            {
                throw new ServerException(
                    "Unable to perform Rackspace authorization using given credentials."
                );
            }

            throw new ServerException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $response = json_decode((string)$response->getBody(), 1);
        foreach ($response['access']['serviceCatalog'] as $location)
        {
            if ($location['name'] == 'cloudFiles')
            {
                foreach ($location['endpoints'] as $server)
                {
                    $this->regions[$server['region']] = $server['publicURL'];
                }
            }
        }

        if (!isset($response['access']['token']['id']))
        {
            throw new ServerException("Unable to fetch rackspace auth token.");
        }

        $this->authToken = $response['access']['token']['id'];

        if ($this->options['cache'])
        {
            $this->store->set(
                $this->options['username'] . '@rackspace-token',
                $this->authToken,
                $this->options['lifetime']
            );

            $this->store->set(
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
     * @return UriInterface
     * @throws ServerException
     */
    protected function buildUri(BucketInterface $bucket, $name)
    {
        if (empty($bucket->getOption('region')))
        {
            throw new ServerException("Every rackspace container should have specified region.");
        }

        $region = $bucket->getOption('region');
        if (!isset($this->regions[$region]))
        {
            throw new ServerException("'{$region}' region is not supported by Rackspace.");
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
     * @return RequestInterface
     */
    protected function buildRequest($method, BucketInterface $bucket, $name, array $headers = [])
    {
        //Adding auth headers
        $headers += [
            'X-Auth-Token' => $this->authToken,
            'Date'         => gmdate('D, d M Y H:i:s T')
        ];

        return new Request($method, $this->buildUri($bucket, $name), $headers);
    }
}