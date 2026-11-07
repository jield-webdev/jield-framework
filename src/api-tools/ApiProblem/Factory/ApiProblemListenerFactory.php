<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Factory;

use Jield\ApiTools\ApiProblem\Listener\ApiProblemListener;
use Psr\Container\ContainerInterface;

class ApiProblemListenerFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ApiProblemListener
    {
        $filters = null;
        $config = [];

        if ($container->has('config')) {
            /** @mago-var mixed $config */
            $config = $container->get('config');
        }

        if (isset($config['api-tools-api-problem']['accept_filters'])) {
            /** @mago-var mixed $filters */
            $filters = $config['api-tools-api-problem']['accept_filters'];
        }

        return new ApiProblemListener(filters: \Jield\Stdlib\Value::nullableArrayOrString($filters));
    }
}
