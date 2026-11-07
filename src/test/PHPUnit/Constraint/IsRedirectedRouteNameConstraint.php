<?php

declare(strict_types=1);

namespace Jield\Test\PHPUnit\Constraint;

use Jield\Mvc\Application;
use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Controller\ControllerManager;
use Jield\Mvc\Controller\Plugin\Url;
use Jield\Mvc\Router\RouteMatch;
use Jield\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use Laminas\Http\Header\Location;
use Laminas\Http\PhpEnvironment\Response;
use Override;
use PHPUnit\Framework\Constraint\Constraint;
use RuntimeException;

use function get_debug_type;
use function is_string;
use function sprintf;

final class IsRedirectedRouteNameConstraint extends Constraint
{
    public function __construct(
        private readonly AbstractHttpControllerTestCase $activeTestCase,
    ) {
    }

    #[Override]
    public function toString(): string
    {
        return 'is the redirected route name';
    }

    /**
     * @param mixed $other
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    #[Override]
    public function matches($other): bool
    {
        if (!is_string($other)) {
            return false;
        }

        $httpResponse = $this->activeTestCase->getResponse();
        if (!$httpResponse instanceof Response) {
            return false;
        }

        $headerLocation = $httpResponse->getHeaders()->get('Location');

        if (!$headerLocation instanceof Location) {
            return false;
        }

        $controllerClass = $this->getControllerFullClass();
        /** @mago-var mixed $urlPlugin */
        $urlPlugin = $controllerClass->plugin('url');

        if (!$urlPlugin instanceof Url) {
            throw new RuntimeException('Url controller plugin not found; cannot determine if URL matches route');
        }

        return $headerLocation->getFieldValue() === $urlPlugin->fromRoute($other);
    }

    /**
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function getControllerFullClass(): AbstractController
    {
        $application = $this->activeTestCase->getApplication();
        if (!$application instanceof Application) {
            throw new RuntimeException('Invalid application instance/value present in controller');
        }

        $routeMatch = $application->getMvcEvent()->getRouteMatch();
        if (!$routeMatch instanceof RouteMatch) {
            throw new RuntimeException(
                'No RouteMatch instance discovered; cannot determine matched controller class name',
            );
        }

        /** @mago-var mixed $controllerIdentifier */
        $controllerIdentifier = $routeMatch->getParam('controller');
        if (!is_string($controllerIdentifier)) {
            throw new RuntimeException('Invalid controller identifier found in route match params');
        }

        /** @mago-var mixed $controllerManager */
        $controllerManager = $this->activeTestCase->getApplicationServiceLocator()->get('ControllerManager');
        if (!$controllerManager instanceof ControllerManager) {
            throw new RuntimeException('Invalid ControllerManager found in ServiceManager');
        }

        /** @mago-var mixed $controller */
        $controller = $controllerManager->get($controllerIdentifier);
        if (!$controller instanceof AbstractController) {
            throw new RuntimeException(sprintf(
                'Invalid controller pulled from ControllerManager by identifier "%s"; received "%s"',
                $controllerIdentifier,
                get_debug_type($controller),
            ));
        }

        return $controller;
    }
}
