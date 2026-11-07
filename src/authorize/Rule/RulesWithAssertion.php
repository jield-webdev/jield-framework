<?php

declare(strict_types=1);

namespace Jield\Authorize\Rule;

use InvalidArgumentException;
use Jield\Authorize\Guard\Route;
use Jield\Authorize\Permissions\Acl\Assertion\AbstractAssertion;
use Jield\Authorize\Service\AuthorizeService;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\Router\RouteMatch;
use Jield\Stdlib\Service;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Webmozart\Assert\Assert;

class RulesWithAssertion extends Route
{
    public function attach(EventManagerInterface $events, $priority = -999): void
    {
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_ROUTE,
            listener: $this->onRoute(...),
            priority: $priority,
        );
    }

    public function onRoute(MvcEvent $event): mixed
    {
        //Grab the ACL from the authorize service
        $acl = Service::get(
            Assert::notNull($event->getApplication())->getServiceManager(),
            AuthorizeService::class,
        )->getAcl();

        foreach ($this->rules as $resource => $rule) {
            if (!isset($rule['assertion'])) {
                continue;
            }

            $assertionClass = $rule['assertion'];
            if ($this->container->has($assertionClass)) {
                $assert = $this->container->get($assertionClass);

                if ($assert instanceof AbstractAssertion) {
                    $assert->setRouteMatch(routeMatch: Assert::isInstanceOf(
                        $event->getRouteMatch(),
                        RouteMatch::class,
                    ));
                    $assert->setRequest(request: $event->getRequest());
                }

                //Do not specify any roles, we do that in the assertion, giving a role would lead to ignorance of the assertion
                $acl->allow(
                    roles: null,
                    resources: $resource,
                    assert: Assert::isInstanceOf($assert, AssertionInterface::class),
                );
            } else {
                throw new InvalidArgumentException(message: sprintf(
                    'Assertion class %s cannot be found',
                    $assertionClass,
                ));
            }
        }
        return null;
    }
}
