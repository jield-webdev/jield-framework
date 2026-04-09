<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Http\PhpEnvironment\Request as HttpRequest;
use Laminas\ServiceManager\Factory\FactoryInterface;

class RequestFactory implements FactoryInterface
{
    /**
     * Create and return a request instance.
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return HttpRequest
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        return new HttpRequest();
    }
}
