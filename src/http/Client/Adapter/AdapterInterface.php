<?php

namespace Jield\Http\Client\Adapter;

use Laminas\Uri\Uri;

/**
 * An interface description for Jield\Http\Client\Adapter classes.
 *
 * These classes are used as connectors for Jield\Http\Client, performing the
 * tasks of connecting, writing, reading and closing connection to the server.
 */
interface AdapterInterface
{
    /**
     * Set the configuration array for the adapter
     *
     * @param array $options
     * @return void
     */
    public function setOptions(array $options = []): void;

    /**
     * Connect to the remote server
     *
     * @param string $host
     * @param int $port
     * @param bool $secure
     * @return void
     */
    public function connect(string $host, int $port = 80, bool $secure = false): void;

    /**
     * Send request to the remote server
     *
     * @param string $method
     * @param Uri $url
     * @param string $httpVer
     * @param array $headers
     * @param string $body
     * @return string Request as text
     */
    public function write(string $method, Uri $url, string $httpVer = '1.1', array $headers = [], string $body = ''): string;

    /**
     * Read response from server
     *
     * @return string
     */
    public function read(): string;

    /**
     * Close the connection to the server
     *
     * @return void
     */
    public function close(): void;
}
