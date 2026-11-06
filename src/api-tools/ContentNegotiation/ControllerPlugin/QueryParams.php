<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;

class QueryParams extends AbstractPlugin
{
    public function __invoke(): array
    {
        $controller = $this->getController();
        if ($controller instanceof AbstractController) {
            /** @var mixed $parameterData */
            $parameterData = $controller->getEvent()->getParam(name: 'LaminasContentNegotiationParameterData');
            if ($parameterData instanceof ParameterDataContainer) {
                return $parameterData->getQueryParams();
            }
        }

        if (!method_exists(object_or_class: \Jield\Stdlib\Value::objectOrString($controller), method: 'getRequest')) {
            return [];
        }

        /** @var mixed $request */
        $request = \Jield\Stdlib\Value::call($controller, 'getRequest');
        if (!method_exists(object_or_class: \Jield\Stdlib\Value::objectOrString($request), method: 'getQuery')) {
            return [];
        }

        return \Jield\Stdlib\Value::array(\Jield\Stdlib\Value::call(\Jield\Stdlib\Value::call($request, 'getQuery'), 'toArray'));
    }
}
