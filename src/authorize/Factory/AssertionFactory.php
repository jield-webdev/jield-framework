<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Permissions\Acl\Assertion\AbstractAssertion;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

final class AssertionFactory implements FactoryInterface
{
    /**
     * @throws \ReflectionException
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AbstractAssertion
    {
        return Assert::isInstanceOf(
            Value::instantiate($requestedName, [$container]),
            \Jield\Authorize\Permissions\Acl\Assertion\AbstractAssertion::class,
        );
    }
}
