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
use Spiral\Cache\StoreInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\StorageException;
use Spiral\Storage\StorageServer;

class RackspaceServer extends StorageServer
{
    /**
     * Some warnings.
     */
    use LoggerTrait;

    /**
     * Default cache lifetime is 24 hours.
     */
    const CACHE_LIFETIME = 86400;

    /**
     * Server configuration, connection options, auth keys and certificates.
     *
     * @var array
     */
    protected $options = [
        'server'     => 'https://auth.api.rackspacecloud.com/v1.0',
        'authServer' => 'https://identity.api.rackspacecloud.com/v2.0/tokens',
        'username'   => '',
        'apiKey'     => '',
        'cache'      => true
    ];

    /**
     * Cache store to remember connection.
     *
     * @invisible
     * @var StoreInterface
     */
    protected $store = null;

    /**
     * Guzzle client.
     *
     * @var Client
     */
    protected $client = null;

    /**
     * Rackspace authentication token.
     *
     * @var string
     */
    protected $authToken = [];

    /**
     * All fetched rackspace regions, some operations can be performed only inside one region.
     *
     * @var array
     */
    protected $regions = [];

    /**
     * Every server represent one virtual storage which can be either local, remote or cloud based.
     * Every server should support basic set of low-level operations (create, move, copy and etc).
     *
     * @param FilesInterface $files   File component.
     * @param array          $options Storage connection options.
     * @param StoreInterface $store   StoreInterface to remember connection credentials across sessions.
     */
    public function __construct(FilesInterface $files, array $options, StoreInterface $store = null)
    {
        parent::__construct($files, $options);

        if (empty($store))
        {
            //This will work only with global container set
            $store = self::getContainer()->get('Spiral\Cache\StoreInterface');
        }

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

            if ($exception->getCode() == 401)
            {
                $this->reconnect();

                return $this->exists($bucket, $name);
            }

            //Some unexpected error
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

        try
        {
            $request = $this->buildRequest(
                'PUT',
                $bucket,
                $name,
                [
                    'Content-Type' => $mimetype,
                    'Etag'         => md5_file($this->castFilename($origin))
                ]
            );

            $this->client->send($request->withBody($this->castStream($origin)));
        }
        catch (ClientException $exception)
        {
            if ($exception->getCode() == 401)
            {
                $this->reconnect();

                return $this->put($bucket, $name, $origin);
            }

            //Some unexpected error
            throw $exception;
        }

        return true;
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
            if ($exception->getCode() == 401)
            {
                $this->reconnect();

                return $this->allocateStream($bucket, $name);
            }

            if ($exception->getCode() != 404)
            {
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
                throw $exception;
            }
        }
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

            throw $exception;
        }

        //Deleting old file
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

            throw $exception;
        }

        return true;
    }

    /**
     * Fetch RackSpace authentication token and build list of region urls.
     */
    protected function connect()
    {
        if (!empty($this->authToken))
        {
            //Already got credentials from cache
            return true;
        }

        //Credentials request
        $request = new Request(
            'POST',
            $this->options['authServer'],
            [
                'Content-Type' => 'application/json'
            ],
            json_encode(
                [
                    'auth' => [
                        'RAX-KSKEY:apiKeyCredentials' => [
                            'username' => $this->options['username'],
                            'apiKey'   => $this->options['apiKey']
                        ]
                    ]
                ]
            )
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
                throw new StorageException(
                    "Unable to perform Rackspace authorization using given credentials."
                );
            }

            throw $exception;
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
            throw new StorageException("Unable to fetch rackspace auth token.");
        }

        $this->authToken = $response['access']['token']['id'];

        if ($this->options['cache'])
        {
            $this->store->set(
                $this->options['username'] . '@rackspace-token',
                $this->authToken,
                self::CACHE_LIFETIME
            );

            $this->store->set(
                $this->options['username'] . '@rackspace-regions',
                $this->regions,
                self::CACHE_LIFETIME
            );
        }

        return true;
    }

    /**
     * Flush existed token and reconnect, can be required if token has expired.
     */
    protected function reconnect()
    {
        $this->authToken = null;
        $this->connect();
        //Check connections status

    }

    /**
     * Create instance of UriInterface based on provided container instance and storage object name.
     * Region url will be automatically resolved and included to generated instance.
     *
     * @param BucketInterface $bucket Bucket instance.
     * @param string          $name   Storage object name.
     * @return UriInterface
     * @throws StorageException
     */
    protected function buildUri(BucketInterface $bucket, $name)
    {
        if (empty($bucket->getOption('region')))
        {
            throw new StorageException("Every rackspace container should have specified region.");
        }

        $region = $bucket->getOption('region');
        if (!isset($this->regions[$region]))
        {
            throw new StorageException("'{$region}' region is not supported by Rackspace.");
        }

        return new Uri(
            $this->regions[$region] . '/' . $bucket->getOption('container') . '/' . rawurlencode($name)
        );
    }

    /**
     * Helper method used to create request with forced authorization token.
     *
     * @param string          $method  Http method.
     * @param BucketInterface $bucket  Bucket instance.
     * @param string          $name    Storage object name.
     * @param array           $headers Request headers.
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