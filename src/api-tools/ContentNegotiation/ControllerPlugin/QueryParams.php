<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Jield\Stdlib\Value;

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

        if (!method_exists(object_or_class: Value::objectOrString($controller), method: 'getRequest')) {
            return [];
        }

        $request = Value::call($controller, 'getRequest');
        if (!method_exists(object_or_class: Value::objectOrString($request), method: 'getQuery')) {
            return [];
        }

        return Value::array(Value::call(
            Value::call($request, 'getQuery'),
            'toArray',
        ));
    }
}
