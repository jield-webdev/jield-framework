<?php

namespace Jield\Http;

use function is_array;

/**
 * Http static client
 */
class ClientStatic
{
    /** @var Client */
    protected static Client $client;

    /**
     * Get the static HTTP client
     *
     * @param array|Traversable|null $options
     * @return Client
     */
    protected static function getStaticClient(Traversable|array|null $options = null): Client
    {
        if (! isset(static::$client) || $options !== null) {
            static::$client = new Client(uri: null, options: $options);
        }
        return static::$client;
    }

    /**
     * HTTP GET METHOD (static)
     *
     * @param string $url
     * @param array $query
     * @param array $headers
     * @param mixed|null $body
     * @param array|Traversable|null $clientOptions
     * @return Response|bool
     */
    public static function get(string $url, array $query = [], array $headers = [], mixed $body = null, Traversable|array|null $clientOptions = null): bool|Response
    {
        if (empty($url)) {
            return false;
        }

        $request = new Request();
        $request->setUri(uri: $url);
        $request->setMethod(method: Request::METHOD_GET);

        if (! empty($query) && is_array(value: $query)) {
            $request->getQuery()->fromArray(values: $query);
        }

        if (! empty($headers) && is_array(value: $headers)) {
            $request->getHeaders()->addHeaders(headers: $headers);
        }

        if (! empty($body)) {
            $request->setContent(value: $body);
        }

        return static::getStaticClient(options: $clientOptions)->send(request: $request);
    }

    /**
     * HTTP POST METHOD (static)
     *
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param mixed|null $body
     * @param array|Traversable|null $clientOptions
     * @return Response|bool
     *@throws Exception\InvalidArgumentException
     */
    public static function post(string $url, array $params, array $headers = [], mixed $body = null, Traversable|array|null $clientOptions = null): bool|Response
    {
        if (empty($url)) {
            return false;
        }

        $request = new Request();
        $request->setUri(uri: $url);
        $request->setMethod(method: Request::METHOD_POST);

        if (! empty($params) && is_array(value: $params)) {
            $request->getPost()->fromArray(values: $params);
        } else {
            throw new Exception\InvalidArgumentException(message: 'The array of post parameters is empty');
        }

        if (! isset($headers['Content-Type'])) {
            $headers['Content-Type'] = Client::ENC_URLENCODED;
        }

        if (! empty($headers) && is_array(value: $headers)) {
            $request->getHeaders()->addHeaders(headers: $headers);
        }

        if (! empty($body)) {
            $request->setContent(value: $body);
        }

        return static::getStaticClient(options: $clientOptions)->send(request: $request);
    }
}
