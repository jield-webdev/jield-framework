<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Jield\Mvc\Exception\RuntimeException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;

class RouteParam extends AbstractPlugin
{
    public function __invoke(?string $param = null, mixed $default = null): mixed
    {
        $controller = $this->getController();

        if (!$controller instanceof InjectApplicationEventInterface) {
            throw new RuntimeException(
                message: 'Controllers must implement Jield\Mvc\InjectApplicationEventInterface to use this plugin.',
            );
        }

        if ($controller instanceof AbstractController) {
            /** @var mixed $parameterData */
            $parameterData = $controller->getEvent()->getParam(name: 'LaminasContentNegotiationParameterData');
            if ($parameterData instanceof ParameterDataContainer) {
                return $parameterData->getRouteParam(name: \Jield\Stdlib\Value::string($param), default: $default);
            }
        }

        $event = $controller->getEvent();
        if (!$event instanceof MvcEvent || $event->getRouteMatch() === null) {
            return $default;
        }

        return \Jield\Stdlib\Value::present($event->getRouteMatch())->getParam(name: \Jield\Stdlib\Value::string($param), default: $default);
    }
}
