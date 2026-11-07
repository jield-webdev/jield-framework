<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Jield\ApiTools\ApiProblem\Listener\SendApiProblemResponseListener;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Http\Response as HttpResponse;
use Laminas\Stdlib\ResponseInterface;
use Psr\Container\ContainerInterface;

class SendApiProblemResponseListenerFactory
{
    public function __invoke(ContainerInterface $container): SendApiProblemResponseListener
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        $displayExceptions =
            isset($config['view_manager']['display_exceptions'])
            && Value::truthy($config['view_manager']['display_exceptions']);

        $listener = new SendApiProblemResponseListener();
        $listener->setDisplayExceptions(flag: $displayExceptions);

        if ($container->has('Response')) {
            $response = Service::get($container, ResponseInterface::class, 'Response');
            if ($response instanceof HttpResponse) {
                $listener->setApplicationResponse(response: $response);
            }
        }

        return $listener;
    }
}
