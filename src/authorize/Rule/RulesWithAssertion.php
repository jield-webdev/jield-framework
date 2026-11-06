<?php

declare(strict_types=1);

namespace Jield\Authorize\Rule;

use Jield\Authorize\Guard\Route;
use Jield\Authorize\Permissions\Acl\Assertion\AbstractAssertion;
use Jield\Authorize\Service\AuthorizeService;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventManagerInterface;

class RulesWithAssertion extends Route
{
    public function attach(EventManagerInterface $events, $priority = -999): void
    {
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_ROUTE,
            listener: [$this, 'onRoute'],
            priority: $priority,
        );
    }

    public function onRoute(MvcEvent $event): mixed
    {
        //Grab the ACL from the authorize service
        $acl = \Jield\Stdlib\Service::get(\Jield\Stdlib\Value::present($event->getApplication())->getServiceManager(), AuthorizeService::class)->getAcl();

        foreach ($this->rules as $resource => $rule) {
            if (!isset($rule['assertion'])) {
                continue;
            }

            /** @var mixed $assertionClass */
            $assertionClass = $rule['assertion'];
            if ($this->container->has(\Jield\Stdlib\Value::string($assertionClass))) {
                /** @var mixed $assert */
                $assert = $this->container->get(\Jield\Stdlib\Value::string($assertionClass));

                if ($assert instanceof AbstractAssertion) {
                    $assert->setRouteMatch(routeMatch: \Jield\Stdlib\Value::instance($event->getRouteMatch(), \Jield\Mvc\Router\RouteMatch::class));
                    $assert->setRequest(request: $event->getRequest());
                }

                //Do not specify any roles, we do that in the assertion, giving a role would lead to ignorance of the assertion
                $acl->allow(roles: null, resources: \Jield\Stdlib\Value::instanceOrArrayOrString($resource, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), assert: \Jield\Stdlib\Value::instance($assert, \Laminas\Permissions\Acl\Assertion\AssertionInterface::class));
            } else {
                throw new \InvalidArgumentException(message: sprintf(
                    'Assertion class %s cannot be found',
                    \Jield\Stdlib\Value::scalar($assertionClass),
                ));
            }
        }
        return null;
    }
}
