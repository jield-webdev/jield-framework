<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\SendResponseListener;
use Psr\Container\ContainerInterface;

class SendResponseListenerFactory
{
    /**
     * @return SendResponseListener
     */
    public function __invoke(ContainerInterface $container): SendResponseListener
    {
        $listener = new SendResponseListener();
        $listener->setEventManager(\Jield\Stdlib\Service::get($container, \Laminas\EventManager\EventManagerInterface::class, 'EventManager'));
        return $listener;
    }
}
