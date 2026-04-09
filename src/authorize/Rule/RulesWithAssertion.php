<?php

declare(strict_types=1);

namespace Jield\Authorize\Rule;

use Jield\Authorize\Guard\Route;
use Jield\Authorize\Permissions\Acl\Assertion\AbstractAssertion;
use Jield\Authorize\Service\AuthorizeService;
use Laminas\EventManager\EventManagerInterface;
use Jield\Mvc\MvcEvent;

class RulesWithAssertion extends Route
{
    public function attach(EventManagerInterface $events, int $priority = -999): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_ROUTE, listener: [$this, 'onRoute'], priority: $priority);
    }

    public function onRoute(MvcEvent $event): void
    {
        //Grab the ACL from the authorize service
        $acl = $event->getApplication()->getServiceManager()->get(AuthorizeService::class)->getAcl();

        foreach ($this->rules as $resource => $rule) {
            if (isset($rule['assertion'])) {
                $assertionClass = $rule['assertion'];
                if ($this->container->has($assertionClass)) {
                    /** @var AbstractAssertion $assert */
                    $assert = $this->container->get($assertionClass);

                    if ($assert instanceof AbstractAssertion) {
                        $assert->setRouteMatch(routeMatch: $event->getRouteMatch());
                        $assert->setRequest(request: $event->getRequest());
                    }

                    //Do not specify any roles, we do that in the assertion, giving a role would lead to ignorance of the assertion
                    $acl->allow(
                        roles:     null,
                        resources: $resource,
                        assert:    $assert
                    );
                } else {
                    throw new \InvalidArgumentException(message: sprintf('Assertion class %s cannot be found', $assertionClass));
                }
            }
        }
    }
}
