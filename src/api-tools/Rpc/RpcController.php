<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rpc;

use Closure;
use Exception;
use Jield\Mvc\Controller\AbstractActionController as BaseAbstractActionController;
use Jield\Mvc\MvcEvent;
use Laminas\View\Model\JsonModel;
use Override;
use function call_user_func_array;
use function is_array;
use function is_callable;
use function is_object;
use function lcfirst;
use function method_exists;
use function str_replace;
use function ucwords;

class RpcController extends BaseAbstractActionController
{
    /** @var null|callable */
    protected $wrappedCallable;

    /**
     * @param callable $wrappedCallable
     * @return void
     */
    public function setWrappedCallable(callable $wrappedCallable): void
    {
        $this->wrappedCallable = $wrappedCallable;
    }

    #[Override]
    public function onDispatch(MvcEvent $e): mixed
    {
        $routeMatch = $e->getRouteMatch();

        $contentNegotiationParams = $e->getParam(name: 'JieldContentNegotiationParameterData');
        $routeParameters = $contentNegotiationParams ? $contentNegotiationParams->getRouteParams() : $routeMatch->getParams();

        $parameterMatcher = new ParameterMatcher(mvcEvent: $e);

        // match route params to dispatchable parameters
        if ($this->wrappedCallable instanceof Closure) {
            $callable = $this->wrappedCallable;
        } elseif (is_array(value: $this->wrappedCallable) && is_callable(value: $this->wrappedCallable)) {
            $callable = $this->wrappedCallable;
        } elseif (is_object(value: $this->wrappedCallable) || null === $this->wrappedCallable) {
            $action   = $routeMatch->getParam(name: 'action', default: 'not-found');
            $method   = static::getMethodFromAction(action: $action);
            $callable = null === $this->wrappedCallable && static::class !== self::class
                ? $this
                : $this->wrappedCallable;
            if (!method_exists(object_or_class: $callable, method: $method)) {
                $method = 'notFoundAction';
            }

            $callable = [$callable, $method];
        } else {
            throw new Exception(message: 'RPC Controller Not Understood');
        }

        $dispatchParameters = $parameterMatcher->getMatchedParameters(callable: $callable, parameters: $routeParameters ?: []);
        $result             = call_user_func_array(callback: $callable, args: $dispatchParameters);

        $e->setParam(name: 'JieldContentNegotiationFallback', value: [JsonModel::class => ['application/json']]);
        $e->setResult(result: $result);
    }

    /**
     * Transform an "action" token into a method name
     *
     * @param string $action
     * @return string
     */
    #[Override]
    public static function getMethodFromAction(string $action): string
    {
        $method = str_replace(search: ['.', '-', '_'], replace: ' ', subject: $action);
        $method = ucwords(string: $method);
        $method = str_replace(search: ' ', replace: '', subject: $method);
        $method = lcfirst(string: $method);

        return $method;
    }
}
