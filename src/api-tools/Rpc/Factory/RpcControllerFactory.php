<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rpc\Factory;

use Jield\ApiTools\Rpc\RpcController;
use Jield\Mvc\Controller\ControllerManager;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function class_exists;
use function explode;
use function is_callable;
use function is_string;
use function sprintf;

class RpcControllerFactory implements AbstractFactoryInterface
{
    /**
     * Marker used to ensure we do not end up in a circular dependency lookup
     * loop.
     *
     * @see https://github.com/zfcampus/zf-rpc/issues/18
     */
    private ?string $lastRequestedControllerService = null;

    /**
     * Determine if we can create a service with name
     *
     * @param string $requestedName
     */
    #[Override]
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        // Prevent circular lookup
        if ($requestedName === $this->lastRequestedControllerService) {
            return false;
        }

        if (!$container->has('config')) {
            return false;
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');
        if (!isset($config['api-tools-rpc'][$requestedName])) {
            return false;
        }

        /** @mago-var mixed $config */
        $config = $config['api-tools-rpc'][$requestedName];
        return isset($config['callable']);
    }

    /**
     * Create and return an RpcController instance.
     *
     * @param string $requestedName
     * @throws ServiceNotCreatedException If the callable configuration value
     *     associated with the controller is not callable.
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RpcController
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        /** @mago-var mixed $callable */
        $callable = Value::path($config, ['api-tools-rpc', $requestedName, 'callable']);

        if (!is_string(value: $callable) && !is_callable(value: $callable)) {
            throw new ServiceNotCreatedException(
                message: 'Unable to create a controller from the configured api-tools-rpc callable',
            );
        }

        if (is_string(value: $callable) && str_contains(haystack: $callable, needle: '::')) {
            $callable = $this->marshalCallable(string: $callable, container: $container);
        }

        $controller = new RpcController();
        $controller->setWrappedCallable(wrappedCallable: Assert::isCallable($callable));
        return $controller;
    }

    /**
     * Marshal an instance method callback from a given string.
     *
     * @param mixed $string String of the form class::method
     * @return callable
     */
    private function marshalCallable(mixed $string, ContainerInterface $container): callable|array
    {
        $callable = false;
        [$class, $method] = explode(separator: '::', string: (string) $string, limit: 2);

        if ($container->has('ControllerManager') && $this->lastRequestedControllerService !== $class) {
            $this->lastRequestedControllerService = $class;
            $callable = $this->marshalCallableFromContainer(
                class: $class,
                method: $method,
                container: Service::get($container, ControllerManager::class, 'ControllerManager'),
            );
        }

        $this->lastRequestedControllerService = null;

        if (!$callable) {
            $callable = $this->marshalCallableFromContainer(class: $class, method: $method, container: $container);
        }

        if ($callable) {
            return Assert::isCallable($callable);
        }

        if (!class_exists(class: $class)) {
            throw new ServiceNotCreatedException(message: sprintf(
                'Cannot create callback %s as class %s does not exist',
                Value::scalar($string),
                $class,
            ));
        }

        return Assert::isCallable([Value::instantiate($class), $method]);
    }

    /**
     * Attempt to marshal a callable from a container.
     */
    private function marshalCallableFromContainer(
        string $class,
        string $method,
        ContainerInterface $container,
    ): callable|false|array {
        if (!$container->has($class)) {
            return false;
        }

        return [$container->get($class), $method];
    }
}
