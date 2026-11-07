<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Jield\Authorize\Guard\Route;
use Jield\Authorize\Rule\RulesWithAssertion;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class RuleWithAssertionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RulesWithAssertion
    {
        return new RulesWithAssertion(
            rules: \Webmozart\Assert\Assert::isArray(Value::path($container->get(Config::class), ['guards', Route::class])),
            container: $container,
        );
    }
}
