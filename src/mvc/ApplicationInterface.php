<?php

namespace Jield\Mvc;

use Laminas\EventManager\EventsCapableInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;

interface ApplicationInterface extends EventsCapableInterface
{
    /**
     * Get the locator object
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceManager(): ServiceLocatorInterface;

    /**
     * Get the request object
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface;

    /**
     * Get the response object
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface;

    /**
     * Run the application
     *
     * @return self
     */
    public function run(): ApplicationInterface;
}
