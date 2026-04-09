<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;

class BodyParams extends AbstractPlugin
{
    /**
     * Grabs a param from body match after content-negotation
     */
    public function __invoke(): array
    {
        $controller = $this->getController();
        if ($controller instanceof AbstractController) {
            $parameterData = $controller->getEvent()->getParam(name: 'JieldContentNegotiationParameterData');
            if ($parameterData instanceof ParameterDataContainer) {
                return $parameterData->getBodyParams();
            }
        }

        if (!method_exists(object_or_class: $controller, method: 'getRequest')) {
            return [];
        }

        $request = $controller->getRequest();
        if (!method_exists(object_or_class: $request, method: 'getPost')) {
            return [];
        }

        return $request->getPost()->toArray();
    }
}
