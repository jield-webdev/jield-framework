<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Mvc\SendResponseListener;

class SendResponseListenerFactory
{
    /**
     * @return SendResponseListener
     */
    public function __invoke(ContainerInterface $container): SendResponseListener
    {
        $listener = new SendResponseListener();
        $listener->setEventManager(eventManager: $container->get('EventManager'));
        return $listener;
    }
}
