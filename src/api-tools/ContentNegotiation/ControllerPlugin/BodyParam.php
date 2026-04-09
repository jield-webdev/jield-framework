<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;

class BodyParam extends AbstractPlugin
{
    /**
     * Grabs a param from body match after content-negotiation
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
                return $parameterData->getBodyParam(name: $param, default: $default);
            }
        }

        if (!method_exists(object_or_class: $controller, method: 'getRequest')) {
            return $default;
        }

        $request = $controller->getRequest();
        if (!method_exists(object_or_class: $request, method: 'getPost')) {
            return $default;
        }

        return $request->getPost(name: $param, default: $default);
    }
}
