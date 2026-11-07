<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Permissions\Acl\Assertion\AbstractAssertion;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class AssertionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AbstractAssertion
    {
        return Value::instance(
            Value::instantiate($requestedName, [$container]),
            \Jield\Authorize\Permissions\Acl\Assertion\AbstractAssertion::class,
        );
    }
}
