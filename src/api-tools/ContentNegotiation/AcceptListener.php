<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\Mvc\Controller\Plugin\AcceptableViewModelSelector;
use Jield\Mvc\InjectApplicationEventInterface;
use Jield\Mvc\MvcEvent;
use Laminas\Stdlib\DispatchableInterface;
use Laminas\View\Model\ModelInterface as ViewModelInterface;
use Webmozart\Assert\Assert;

use function is_array;
use function is_string;
use function method_exists;

class AcceptListener
{
    protected AcceptableViewModelSelector $selector;

    protected array $controllerConfig = [];

    protected array $selectorsConfig = [];

    public function __construct(AcceptableViewModelSelector $selector, array $config)
    {
        $this->selector = $selector;

        if (isset($config['controllers']) && is_array(value: $config['controllers'])) {
            $this->controllerConfig = $config['controllers'];
        }

        if (isset($config['selectors']) && is_array(value: $config['selectors'])) {
            $this->selectorsConfig = $config['selectors'];
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function __invoke(MvcEvent $e): ?ApiProblemResponse
    {
        $request = $e->getRequest();
        if (!method_exists(object_or_class: $request, method: 'getHeaders')) {
            // Should only trigger on HTTP requests
            return null;
        }

        /** @mago-var mixed $result */
        $result = $e->getResult();
        if (!is_array(value: $result) && !$result instanceof ViewModel) {
            // We will only attempt to re-cast ContentNegotiation\ViewModel
            // results or arrays to what the AcceptableViewModelSelector gives
            // us. Anything else, we cannot handle.
            return null;
        }

        $controller = $e->getTarget();
        if (!$controller instanceof InjectApplicationEventInterface) {
            // The AcceptableViewModelSelector needs a controller that is
            // event-aware in order to work; if it's not, we cannot do
            // anything more.
            return null;
        }

        $selector = $this->selector;
        $selector->setController(controller: Assert::isInstanceOf($controller, DispatchableInterface::class));

        /** @mago-var mixed $criteria */
        $criteria = $e->getParam(name: 'LaminasContentNegotiation');

        // If the criteria from the LaminasContentNegotiation parameter is a string,
        // attempt to get it via a selector.
        if (is_string(value: $criteria)) {
            $criteria = $this->getCriteria(criteria: $criteria);
        }

        // If we have no criteria, derive it from configuration and/or any set fallbacks
        if (!$criteria) {
            /** @mago-var mixed $fallbackConfig */
            $fallbackConfig = $e->getParam(name: 'LaminasContentNegotiationFallback');
            /** @mago-var mixed $controllerName */
            $controllerName = Assert::notNull($e->getRouteMatch())->getParam(name: 'controller');

            $criteria = $this->getSelectorCriteria(fallbackConfig: Assert::nullOrString($fallbackConfig), controllerName: Assert::nullOrString($controllerName));
        }

        // Retrieve a view model based on the criteria
        $useDefault = false;
        if (empty($criteria)) {
            $useDefault = true;
        }

        $viewModel = $selector(matchAgainst: Assert::nullOrIsArray($criteria), returnDefault: $useDefault);

        if (!$viewModel instanceof ViewModelInterface) {
            return new ApiProblemResponse(apiProblem: new ApiProblem(
                status: 406,
                detail: 'Unable to resolve Accept header to a representation',
            ));
        }

        // Populate the view model with the result...
        $this->populateViewModel(result: $result, viewModel: $viewModel, e: $e);
        return null;
    }

    /**
     * Derive the view model selector criteria
     *
     * Try and determine the view model selection criteria based on the configuration
     * for the current controller service name, using a fallback if it exists.
     */
    protected function getSelectorCriteria(?string $fallbackConfig = null, ?string $controllerName = null): ?array
    {
        if ($this->controllerConfig === []) {
            return $this->getCriteria(criteria: $fallbackConfig);
        }

        // get the controllers from the content-neg configuration
        $controllers = $this->controllerConfig;

        // if there is no config for this controller, move on
        if (!$controllerName || !isset($controllers[$controllerName])) {
            return $this->getCriteria(criteria: $fallbackConfig);
        }

        // Retrieve the criteria; if none found, or invalid, use the fallback.
        /** @mago-var mixed $criteria */
        $criteria = $controllers[$controllerName];

        return $this->getCriteria(criteria: \Jield\Stdlib\Value::nullableArrayOrString($criteria)) ?: $this->getCriteria(criteria: $fallbackConfig);
    }

    /**
     * Populate the view model returned by the AcceptableViewModelSelector from the result
     *
     * If the result is a ViewModel, we "re-cast" it by copying over all
     * values/settings/etc from the original.
     *
     * If the result is an array, we pass those values as the view model variables.
     */
    protected function populateViewModel(ViewModel|array $result, ViewModelInterface $viewModel, MvcEvent $e): void
    {
        if ($result instanceof ViewModel) {
            // "Re-cast" content-negotiation view models to the view model type
            // selected by the AcceptableViewModelSelector

            $viewModel->setVariables(variables: $result->getVariables());
            $viewModel->setTemplate(template: Assert::stringNotEmpty($result->getTemplate()));
            $viewModel->setCaptureTo(capture: $result->captureTo());
            $viewModel->setTerminal($result->terminate());
            $viewModel->setAppend(append: $result->isAppend());
            if ($result->hasChildren()) {
                foreach ($result->getChildren() as $child) {
                    $viewModel->addChild(child: $child);
                }
            }

            $e->setResult(result: $viewModel);
            return;
        }

        // At this point, the result is an array; use it to populate the view
        // model variables
        $viewModel->setVariables(variables: \Jield\Stdlib\Value::namedIterable($result));
        $e->setResult(result: $viewModel);
    }

    /**
     * Return criteria
     *
     * If the criteria is an array, return it directly.
     *
     * If the criteria is a string, attempt to look it up in the registered selectors;
     * if found, return that criteria.
     *
     * Otherwise, return nothing.
     */
    protected function getCriteria(array|string|null $criteria = null): ?array
    {
        // if it's an array, that means we have direct configuration
        if (is_array(value: $criteria)) {
            return $criteria;
        }

        // if it's a string, we should try to resolve that key to a reusable selector set
        if (is_string(value: $criteria) && isset($this->selectorsConfig[$criteria])) {
            /** @mago-var mixed $criteria */
            $criteria = $this->selectorsConfig[$criteria];
            if (!empty($criteria)) {
                return Assert::isArray($criteria);
            }
        }

        return null;
    }
}
