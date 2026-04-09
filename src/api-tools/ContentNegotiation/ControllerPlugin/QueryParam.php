<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;

class QueryParam extends AbstractPlugin
{
    /**
     * Grabs a param from route match by default.
     *
     * @param string|null $param
     * @param mixed|null $default
     * @return mixed
     */
    public function __invoke(?string $param = null, mixed $default = null): mixed
    {
        $controller = $this->getController();
        if ($controller instanceof AbstractController) {
            $parameterData = $controller->getEvent()->getParam(name: 'JieldContentNegotiationParameterData');
            if ($parameterData instanceof ParameterDataContainer) {
                return $parameterData->getQueryParam(name: $param, default: $default);
            }
        }

        if (!method_exists(object_or_class: $controller, method: 'getRequest')) {
            return $default;
        }

        $request = $controller->getRequest();
        if (!method_exists(object_or_class: $request, method: 'getQuery')) {
            return $default;
        }

        return $request->getQuery(name: $param, default: $default);
    }
}
