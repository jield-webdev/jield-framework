<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\SendResponseListener;
use Jield\Stdlib\Service;
use Laminas\EventManager\EventManagerInterface;
use Psr\Container\ContainerInterface;

class SendResponseListenerFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SendResponseListener
    {
        $listener = new SendResponseListener();
        $listener->setEventManager(Service::get($container, EventManagerInterface::class, 'EventManager'));
        return $listener;
    }
}
