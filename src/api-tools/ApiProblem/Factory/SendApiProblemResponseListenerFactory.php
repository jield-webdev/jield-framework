<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Jield\ApiTools\ApiProblem\Listener\SendApiProblemResponseListener;
use Laminas\Http\Response as HttpResponse;
use Psr\Container\ContainerInterface;

class SendApiProblemResponseListenerFactory
{
    public function __invoke(ContainerInterface $container): SendApiProblemResponseListener
    {
        /** @var mixed $config */
        $config = $container->get('config');
        $displayExceptions =
            isset($config['view_manager']['display_exceptions']) && \Jield\Stdlib\Value::truthy($config['view_manager']['display_exceptions']);

        $listener = new SendApiProblemResponseListener();
        $listener->setDisplayExceptions(flag: $displayExceptions);

        if ($container->has('Response')) {
            $response = \Jield\Stdlib\Service::get($container, \Laminas\Stdlib\ResponseInterface::class, 'Response');
            if ($response instanceof HttpResponse) {
                $listener->setApplicationResponse(response: $response);
            }
        }

        return $listener;
    }
}
