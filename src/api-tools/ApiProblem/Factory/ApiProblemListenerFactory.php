<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Psr\Container\ContainerInterface;
use Jield\ApiTools\ApiProblem\Listener\ApiProblemListener;

class ApiProblemListenerFactory
{
    public function __invoke(ContainerInterface $container): ApiProblemListener
    {
        $filters = null;
        $config  = [];

        if ($container->has(id: 'config')) {
            $config = $container->get('config');
        }

        if (isset($config['api-tools-api-problem']['accept_filters'])) {
            $filters = $config['api-tools-api-problem']['accept_filters'];
        }

        return new ApiProblemListener(filters: $filters);
    }
}
