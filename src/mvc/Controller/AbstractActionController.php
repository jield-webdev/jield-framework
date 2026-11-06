<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller;

use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Model\ViewModel;

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
    public function indexAction(): mixed
    {
        return new ViewModel([
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
        $event = $this->getEvent();
        $routeMatch = $event->getRouteMatch();
        \Jield\Stdlib\Value::present($routeMatch)->setParam('action', 'not-found');

        /** @var mixed $helper */
        $helper = $this->plugin('createHttpNotFoundModel');
        return \Jield\Stdlib\Value::instance($helper($event->getResponse()), \Jield\Mvc\View\Model\ViewModel::class);
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
        if (!$routeMatch) {
            /**
             * @todo Determine requirements for when route match is missing.
             *       Potentially allow pulling directly from request metadata?
             */
            throw new DomainException('Missing route matches; unsure how to retrieve action');
        }

        /** @var mixed $action */
        $action = $routeMatch->getParam('action', 'not-found');
        $method = static::getMethodFromAction(\Jield\Stdlib\Value::string($action));

        if (!method_exists($this, $method)) {
            $method = 'notFoundAction';
        }

        /** @var mixed $actionResponse */
        $actionResponse = \Jield\Stdlib\Value::call($this, $method, [], scope: self::class);

        $e->setResult($actionResponse);

        return $actionResponse;
    }
}
