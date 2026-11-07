<?php

declare(strict_types=1);

namespace Jield\Search\Factory;

use Jield\Search\Service\SearchUpdateService;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class SearchUpdateServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): SearchUpdateService
    {
        return new SearchUpdateService(
            container: $container,
            bus: Value::instance(
                $container->get('Jield\Search\Command\Bus'),
                MessageBusInterface::class,
            ),
            cores: Value::array($container->get('config')['search']['cores'] ?? []),
        );
    }
}
