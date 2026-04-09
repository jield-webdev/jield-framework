<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Psr\Container\ContainerInterface;
use Jield\ApiTools\ApiProblem\Listener\SendApiProblemResponseListener;
use Jield\Http\Response as HttpResponse;

class SendApiProblemResponseListenerFactory
{
    public function __invoke(ContainerInterface $container): SendApiProblemResponseListener
    {
        $config            = $container->get('config');
        $displayExceptions = isset($config['view_manager'])
            && isset($config['view_manager']['display_exceptions'])
            && $config['view_manager']['display_exceptions'];

        $listener = new SendApiProblemResponseListener();
        $listener->setDisplayExceptions(flag: $displayExceptions);

        if ($container->has(id: 'Response')) {
            $response = $container->get('Response');
            if ($response instanceof HttpResponse) {
                $listener->setApplicationResponse(response: $response);
            }
        }

        return $listener;
    }
}
