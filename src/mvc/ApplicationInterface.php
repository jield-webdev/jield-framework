<?php

namespace Jield\Mvc;

use Laminas\EventManager\EventsCapableInterface;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;
use Psr\Container\ContainerInterface;

interface ApplicationInterface extends EventsCapableInterface
{
    /**
     * Get the locator object
     */
    public function getServiceManager(): ContainerInterface;

    /**
     * Get the request object
     */
    public function getRequest(): RequestInterface;

    /**
     * Get the response object
     */
    public function getResponse(): ResponseInterface;

    /**
     * Run the application
     */
    public function run(): ApplicationInterface;
}
