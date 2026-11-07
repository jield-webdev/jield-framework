<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

class QueryParam extends AbstractPlugin
{
    /**
     * Grabs a param from route match by default.
     *
     * @throws \ReflectionException
     */
    public function __invoke(?string $param = null, mixed $default = null): mixed
    {
        $controller = $this->getController();
        if ($controller instanceof AbstractController) {
            /** @mago-var mixed $parameterData */
            $parameterData = $controller->getEvent()->getParam(name: 'LaminasContentNegotiationParameterData');
            if ($parameterData instanceof ParameterDataContainer) {
                return $parameterData->getQueryParam(name: Assert::string($param), default: $default);
            }
        }

        if (!method_exists(object_or_class: Value::objectOrString($controller), method: 'getRequest')) {
            return $default;
        }

        /** @mago-var mixed $request */
        $request = Value::call($controller, 'getRequest');
        if (!method_exists(object_or_class: Value::objectOrString($request), method: 'getQuery')) {
            return $default;
        }

        return Value::call($request, 'getQuery', [$param, $default]);
    }
}
