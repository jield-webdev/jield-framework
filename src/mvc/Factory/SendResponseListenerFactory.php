<?php

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
        $listener->setEventManager($container->get('EventManager'));
        return $listener;
    }
}
