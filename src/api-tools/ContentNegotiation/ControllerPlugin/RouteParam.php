<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Jield\Mvc\Exception\RuntimeException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Webmozart\Assert\Assert;

class RouteParam extends AbstractPlugin
{
    /**
     * @throws RuntimeException
     */
    public function __invoke(?string $param = null, mixed $default = null): mixed
    {
        $controller = $this->getController();

        if (!$controller instanceof InjectApplicationEventInterface) {
            throw new RuntimeException(
                message: 'Controllers must implement Jield\Mvc\InjectApplicationEventInterface to use this plugin.',
            );
        }

        if ($controller instanceof AbstractController) {
            /** @mago-var mixed $parameterData */
            $parameterData = $controller->getEvent()->getParam(name: 'LaminasContentNegotiationParameterData');
            if ($parameterData instanceof ParameterDataContainer) {
                return $parameterData->getRouteParam(name: Assert::string($param), default: $default);
            }
        }

        $event = $controller->getEvent();
        if (!$event instanceof MvcEvent || $event->getRouteMatch() === null) {
            return $default;
        }

        return Assert::notNull($event->getRouteMatch())->getParam(name: Assert::string($param), default: $default);
    }
}
