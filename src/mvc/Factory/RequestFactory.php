<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Laminas\Http\PhpEnvironment\Request as HttpRequest;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class RequestFactory implements FactoryInterface
{
    /**
     * Create and return a request instance.
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return HttpRequest
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): HttpRequest
    {
        return new HttpRequest();
    }
}
