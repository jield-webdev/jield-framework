<?php

declare(strict_types=1);

namespace Jield\Search\Factory;

use Jield\Search\Service\SearchUpdateService;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class SearchUpdateServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): SearchUpdateService
    {
        return new SearchUpdateService(
            container: $container,
            bus: \Jield\Stdlib\Value::instance($container->get('Jield\Search\Command\Bus'), \Symfony\Component\Messenger\MessageBusInterface::class),
            cores: \Jield\Stdlib\Value::array($container->get('config')['search']['cores'] ?? []),
        );
    }
}
