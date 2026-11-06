<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Laminas\Http\PhpEnvironment\Response as HttpResponse;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ResponseFactory implements FactoryInterface
{
    /**
     * Create and return a response instance.
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return HttpResponse
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): HttpResponse
    {
        return new HttpResponse();
    }
}
