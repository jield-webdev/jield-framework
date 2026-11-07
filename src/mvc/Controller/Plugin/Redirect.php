<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Value;
use Laminas\Http\Response;

use function is_scalar;
use function method_exists;

class Redirect extends AbstractPlugin
{
    /** @var MvcEvent|null */
    protected ?MvcEvent $event = null;
    /** @var Response|null */
    protected ?Response $response = null;

    /**
     * Generate redirect response based on given route
     *
     * @param  string $route RouteInterface name
     * @param  array $params Parameters to use in url generation, if any
     * @param array|bool $options RouteInterface-specific options to use in url generation, if any
     * @param  bool $reuseMatchedParams Whether to reuse matched parameters
     * @return Response
     * @throws Exception\DomainException If composed controller does not implement InjectApplicationEventInterface, or
     *         router cannot be found in controller event.
     */
    public function toRoute($route = null, $params = [], $options = [], $reuseMatchedParams = false): Response
    {
        $controller = $this->getController();
        if (!$controller || !method_exists($controller, 'plugin')) {
            throw new DomainException('Redirect plugin requires a controller that defines the plugin() method');
        }

        /** @var mixed $urlPlugin */
        $urlPlugin = $controller->plugin('url');

        if (is_scalar($options)) {
            $url = Value::call($urlPlugin, 'fromRoute', [$route, $params, $options]);
        } else {
            $url = Value::call($urlPlugin, 'fromRoute', [$route, $params, $options, $reuseMatchedParams]);
        }

        return $this->toUrl(Value::string($url));
    }

    /**
     * Generate redirect response based on given URL
     *
     * @param  string $url
     * @return Response
     */
    public function toUrl($url): Response
    {
        $response = $this->getResponse();
        Value::present($response)->getHeaders()->addHeaderLine('Location', $url);
        Value::present($response)->setStatusCode(302);
        return $response;
    }

    /**
     * Refresh to current route
     *
     * @return Response
     */
    public function refresh(): Response
    {
        return $this->toRoute(null, [], [], true);
    }

    /**
     * Get the response
     *
     * @return Response
     * @throws Exception\DomainException If unable to find response.
     */
    protected function getResponse(): Response
    {
        if ($this->response) {
            return $this->response;
        }

        $event = $this->getEvent();
        $response = Value::present($event)->getResponse();
        if (!$response instanceof Response) {
            throw new DomainException('Redirect plugin requires event compose a response');
        }
        $this->response = $response;
        return $this->response;
    }

    /**
     * Get the event
     *
     * @return MvcEvent
     * @throws Exception\DomainException If unable to find event.
     */
    protected function getEvent(): ?MvcEvent
    {
        if ($this->event) {
            return $this->event;
        }

        $controller = $this->getController();
        if (!$controller instanceof InjectApplicationEventInterface) {
            throw new DomainException(
                'Redirect plugin requires a controller that implements InjectApplicationEventInterface',
            );
        }

        $event = $controller->getEvent();
        if (!$event instanceof MvcEvent) {
            $params = $event->getParams();
            $event = new MvcEvent();
            $event->setParams($params);
        }
        $this->event = $event;

        return $this->event;
    }
}
