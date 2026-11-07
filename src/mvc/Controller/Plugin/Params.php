<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin;

use Jield\Mvc\Controller\AbstractController;
use Jield\Mvc\Exception\RuntimeException;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Laminas\Http\Request;
use Webmozart\Assert\Assert;

class Params extends AbstractPlugin
{
    /**
     * Grabs a param from route match by default.
     *
     * @param string $param
     * @return mixed
     */
    public function __invoke($param = null, mixed $default = null): mixed
    {
        if ($param === null) {
            return $this;
        }
        return $this->fromRoute($param, $default);
    }

    /** @return mixed All file metadata, a single file, or the default value. */
    public function fromFiles(?string $name = null, mixed $default = null): mixed
    {
        $request = $this->httpRequest();
        return $name === null ? $request->getFiles()->toArray() : $request->getFiles($name, $default);
    }

    /** @return mixed All headers, a single header, or the default value. */
    public function fromHeader(?string $header = null, mixed $default = null): mixed
    {
        $headers = $this->httpRequest()->getHeaders();
        if ($header === null) {
            return $headers->toArray();
        }
        return $headers->has($header) ? $headers->get($header) : $default;
    }

    public function fromPost(?string $param = null, mixed $default = null): mixed
    {
        $request = $this->httpRequest();
        return $param === null ? $request->getPost()->toArray() : $request->getPost($param, $default);
    }

    public function fromQuery(?string $param = null, mixed $default = null): mixed
    {
        $request = $this->httpRequest();
        return $param === null ? $request->getQuery()->toArray() : $request->getQuery($param, $default);
    }

    private function httpRequest(): Request
    {
        $controller = $this->getController();
        if (!$controller instanceof AbstractController) {
            throw new RuntimeException('This plugin requires a controller with an HTTP request.');
        }
        $request = $controller->getRequest();
        if (!$request instanceof Request) {
            throw new RuntimeException('This plugin requires an HTTP request.');
        }
        return $request;
    }

    /**
     * Return all route parameters or a single route parameter.
     *
     * @param string $param Parameter name to retrieve, or null to get all.
     * @param mixed $default Default value to use when the parameter is missing.
     * @return mixed
     * @throws RuntimeException
     */
    public function fromRoute($param = null, mixed $default = null): mixed
    {
        $controller = $this->getController();

        if (!$controller instanceof InjectApplicationEventInterface) {
            throw new RuntimeException(
                'Controllers must implement Jield\Mvc\InjectApplicationEventInterface to use this plugin.',
            );
        }

        if ($param === null) {
            return Assert::notNull(
                Assert::isInstanceOf($controller->getEvent(), MvcEvent::class)->getRouteMatch(),
            )->getParams();
        }

        return Assert::notNull(
            Assert::isInstanceOf($controller->getEvent(), MvcEvent::class)->getRouteMatch(),
        )->getParam($param, $default);
    }
}
