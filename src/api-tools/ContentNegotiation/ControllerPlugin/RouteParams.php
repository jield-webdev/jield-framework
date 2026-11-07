<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\ControllerPlugin;

use Jield\ApiTools\ContentNegotiation\ParameterDataContainer;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Jield\Mvc\Exception\RuntimeException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;

class RouteParams extends AbstractPlugin
{
    /**
     * @throws RuntimeException
     */
    public function __invoke(): array
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
                return $parameterData->getRouteParams();
            }
        }

        $event = $controller->getEvent();
        if (!$event instanceof MvcEvent || $event->getRouteMatch() === null) {
            return [];
        }

        return \Webmozart\Assert\Assert::notNull($event->getRouteMatch())->getParams();
    }
}
