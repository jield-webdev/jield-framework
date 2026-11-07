<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller;

use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Model\ViewModel;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

use function method_exists;

/**
 * Basic action controller
 */
abstract class AbstractActionController extends AbstractController
{
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
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function notFoundAction(): ViewModel
    {
        $event = $this->getEvent();
        $routeMatch = $event->getRouteMatch();
        Assert::notNull($routeMatch)->setParam('action', 'not-found');

        $helper = Assert::isCallable($this->plugin('createHttpNotFoundModel'));
        return Assert::isInstanceOf($helper($event->getResponse()), ViewModel::class);
    }

    /**
     * Execute the request
     *
     * @throws Exception\DomainException
     * @throws \ReflectionException
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

        /** @mago-var mixed $action */
        $action = $routeMatch->getParam('action', 'not-found');
        $method = static::getMethodFromAction(Assert::string($action));

        if (!method_exists($this, $method)) {
            $method = 'notFoundAction';
        }

        /** @mago-var mixed $actionResponse */
        $actionResponse = Value::call($this, $method, [], scope: self::class);

        $e->setResult($actionResponse);

        return $actionResponse;
    }
}
