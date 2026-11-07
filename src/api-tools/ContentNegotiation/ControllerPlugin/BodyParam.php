<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

class BodyParam extends AbstractPlugin
{
    /**
     * Grabs a param from body match after content-negotiation
     *
     * @param string|null $param
     * @param mixed $default
     * @return mixed
     */
    public function __invoke(?string $param = null, mixed $default = null): mixed
    {
        $controller = $this->getController();
        if ($controller instanceof AbstractController) {
            $parameterData = $controller->getEvent()->getParam(name: 'LaminasContentNegotiationParameterData');
            if ($parameterData instanceof ParameterDataContainer) {
                return $parameterData->getBodyParam(name: Assert::string($param), default: $default);
            }
        }

        if (!method_exists(object_or_class: $controller, method: 'getRequest')) {
            return $default;
        }

        $request = Value::call($controller, 'getRequest');
        if (!method_exists(object_or_class: $request, method: 'getPost')) {
            return $default;
        }

        return Value::call($request, 'getPost', ['name' => $param, 'default' => $default]);
    }
}
