<?php

namespace Jield\Mvc\Controller;

use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\MvcEvent;
use Laminas\View\Model\ViewModel;

use function method_exists;

/**
 * Basic action controller
 */
abstract class AbstractActionController extends AbstractController
{
    /**
     * {@inheritDoc}
     */
    protected string|array|null $eventIdentifier = self::class;

    /**
     * Default action if none provided
     *
     * @return ViewModel
     */
    public function indexAction(): ViewModel
    {
        return new ViewModel(variables: [
            'content' => 'Placeholder page',
        ]);
    }

    /**
     * Action called if matched action does not exist
     *
     * @return ViewModel
     */
    public function notFoundAction(): ViewModel
    {
        $event      = $this->getEvent();
        $routeMatch = $event->getRouteMatch();
        $routeMatch->setParam(name: 'action', value: 'not-found');

        $helper = $this->plugin(name: 'createHttpNotFoundModel');
        return $helper($event->getResponse());
    }

    /**
     * Execute the request
     *
     * @return mixed
     * @throws Exception\DomainException
     */
    public function onDispatch(MvcEvent $e): mixed
    {
        $routeMatch = $e->getRouteMatch();
        if (! $routeMatch) {
            /**
             * @todo Determine requirements for when route match is missing.
             *       Potentially allow pulling directly from request metadata?
             */
            throw new DomainException(message: 'Missing route matches; unsure how to retrieve action');
        }

        $action = $routeMatch->getParam(name: 'action', default: 'not-found');
        $method = static::getMethodFromAction(action: $action);

        if (! method_exists(object_or_class: $this, method: $method)) {
            $method = 'notFoundAction';
        }

        $actionResponse = $this->$method();

        $e->setResult(result: $actionResponse);

        return $actionResponse;
    }
}
