<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Guard\Route;
use Jield\Authorize\Rule\RulesWithAssertion;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Jield\Authorize\Config;

class RuleWithAssertionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RulesWithAssertion
    {
        return new RulesWithAssertion(rules: $container->get(Config::class)['guards'][Route::class], container: $container);
    }
}
