<?php

namespace Jield\Mvc\Controller\Plugin;

use Jield\Http\Response;
use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;

use function is_scalar;
use function method_exists;

class Redirect extends AbstractPlugin
{
    /** @var MvcEvent|null */
    protected ?MvcEvent $event;
    /** @var Response|null */
    protected ?Response $response;

    /**
     * Generate redirect response based on given route
     *
     * @param string|null $route RouteInterface name
     * @param array $params Parameters to use in url generation, if any
     * @param array $options RouteInterface-specific options to use in url generation, if any
     * @param bool $reuseMatchedParams Whether to reuse matched parameters
     * @return Response
     * @throws Exception\DomainException If composed controller does not implement InjectApplicationEventInterface, or
     *         router cannot be found in controller event.
     */
    public function toRoute(?string $route = null, array $params = [], array $options = [], bool $reuseMatchedParams = false): ?Response
    {
        $controller = $this->getController();
        if (! $controller || ! method_exists(object_or_class: $controller, method: 'plugin')) {
            throw new DomainException(
                message: 'Redirect plugin requires a controller that defines the plugin() method'
            );
        }

        $urlPlugin = $controller->plugin(name: 'url');

        if (is_scalar(value: $options)) {
            $url = $urlPlugin->fromRoute($route, $params, $options);
        } else {
            $url = $urlPlugin->fromRoute($route, $params, $options, $reuseMatchedParams);
        }

        return $this->toUrl(url: $url);
    }

    /**
     * Generate redirect response based on given URL
     *
     * @param string $url
     * @return Response
     */
    public function toUrl(string $url): ?Response
    {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine(headerFieldNameOrLine: 'Location', fieldValue: $url);
        $response->setStatusCode(code: 302);
        return $response;
    }

    /**
     * Refresh to current route
     *
     * @return Response
     */
    public function refresh(): ?Response
    {
        return $this->toRoute(route: null, params: [], options: [], reuseMatchedParams: true);
    }

    /**
     * Get the response
     *
     * @return Response
     * @throws Exception\DomainException If unable to find response.
     */
    protected function getResponse(): ?Response
    {
        if ($this->response) {
            return $this->response;
        }

        $event    = $this->getEvent();
        $response = $event->getResponse();
        if (! $response instanceof Response) {
            throw new DomainException(message: 'Redirect plugin requires event compose a response');
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
        if (! $controller instanceof InjectApplicationEventInterface) {
            throw new DomainException(
                message: 'Redirect plugin requires a controller that implements InjectApplicationEventInterface'
            );
        }

        $event = $controller->getEvent();
        if (! $event instanceof MvcEvent) {
            $params = $event->getParams();
            $event  = new MvcEvent();
            $event->setParams(params: $params);
        }
        $this->event = $event;

        return $this->event;
    }
}
