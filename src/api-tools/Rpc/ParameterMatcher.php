<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rpc;

use Closure;
use Exception;
use Laminas\Http\PhpEnvironment\Request as PhpEnvironmentRequest;
use Laminas\Http\PhpEnvironment\Response as PhpEnvironmentResponse;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Jield\Mvc\Application;
use Jield\Mvc\ApplicationInterface;
use Jield\Mvc\MvcEvent;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionObject;

use function count;
use function is_array;
use function is_string;
use function is_subclass_of;
use function sprintf;
use function str_replace;
use function strtolower;

class ParameterMatcher
{
    /** @var MvcEvent  */
    protected MvcEvent $mvcEvent;

    public function __construct(MvcEvent $mvcEvent)
    {
        $this->mvcEvent = $mvcEvent;
    }

    /**
     * @param callable $callable
     * @param array $parameters
     * @return array
     * @throws ReflectionException
     */
    public function getMatchedParameters(callable $callable, array $parameters): array
    {
        if (is_string(value: $callable) || $callable instanceof Closure) {
            $reflection       = new ReflectionFunction(function: $callable);
            $reflMethodParams = $reflection->getParameters();
        } elseif (is_array(value: $callable) && count(value: $callable) === 2) {
            $object           = $callable[0];
            $method           = $callable[1];
            $reflection       = new ReflectionObject(object: $object);
            $reflMethodParams = $reflection->getMethod(name: $method)->getParameters();
        } else {
            throw new Exception(message: 'Unknown callable');
        }

        $dispatchParams = [];

        // normalize names to that they can match potential php variables
        $normalParams = [];
        foreach ($parameters as $pn => $pv) {
            $normalParams[str_replace(search: ['-', '_'], replace: '', subject: strtolower(string: $pn))] = $pv;
        }

        foreach ($reflMethodParams as $reflMethodParam) {
            $paramName             = $reflMethodParam->getName();
            $normalMethodParamName = str_replace(search: ['-', '_'], replace: '', subject: strtolower(string: $paramName));
            $reflectionType        = $reflMethodParam->getType();
            if ($reflectionType instanceof ReflectionNamedType && ! $reflectionType->isBuiltin()) {
                $typehint = $reflectionType->getName();

                if (
                    $typehint === PhpEnvironmentRequest::class
                    || $typehint === Request::class
                    || $typehint === RequestInterface::class
                    || is_subclass_of(object_or_class: $typehint, class: RequestInterface::class)
                ) {
                    $dispatchParams[] = $this->mvcEvent->getRequest();
                    continue;
                }

                if (
                    $typehint === PhpEnvironmentResponse::class
                    || $typehint === Response::class
                    || $typehint === ResponseInterface::class
                    || is_subclass_of(object_or_class: $typehint, class: ResponseInterface::class)
                ) {
                    $dispatchParams[] = $this->mvcEvent->getResponse();
                    continue;
                }

                if (
                    $typehint === ApplicationInterface::class
                    || $typehint === Application::class
                    || is_subclass_of(object_or_class: $typehint, class: ApplicationInterface::class)
                ) {
                    $dispatchParams[] = $this->mvcEvent->getApplication();
                    continue;
                }

                if (
                    $typehint === MvcEvent::class
                    || is_subclass_of(object_or_class: $typehint, class: MvcEvent::class)
                ) {
                    $dispatchParams[] = $this->mvcEvent;
                    continue;
                }

                throw new Exception(message: sprintf(
                    '%s was requested, but could not be auto-bound',
                    $typehint
                ));
            }

            if (isset($normalParams[$normalMethodParamName])) {
                $dispatchParams[] = $normalParams[$normalMethodParamName];
            } else {
                if ($reflMethodParam->isOptional()) {
                    $dispatchParams[] = $reflMethodParam->getDefaultValue();
                    continue;
                }

                $dispatchParams[] = null;
            }
        }

        return $dispatchParams;
    }
}
