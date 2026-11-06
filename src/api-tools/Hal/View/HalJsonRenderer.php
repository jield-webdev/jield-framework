<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\View;

use ArrayAccess;
use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\View\ApiProblemModel;
use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Jield\ApiTools\Hal\Collection;
use Jield\ApiTools\Hal\Entity;
use Jield\ApiTools\Hal\Plugin\Hal;
use Jield\Mvc\MvcEvent as ViewEvent;
use Jield\Mvc\View\Renderer\JsonRenderer;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\ModelInterface;
use Override;

/**
 * Handles rendering of the following:
 *
 * - API-Problem
 * - HAL collections
 * - HAL resources
 */
class HalJsonRenderer extends JsonRenderer
{
    protected ?HelperPluginManager $helpers = null;

    protected ?ViewEvent $viewEvent = null;

    public function __construct(
        protected ApiProblemRenderer $apiProblemRenderer,
    ) {}

    /**
     * Set helper plugin manager instance.
     *
     * Also ensures that the 'Hal' helper is present.
     *
     */
    public function setHelperPluginManager(HelperPluginManager $helpers): void
    {
        $this->helpers = $helpers;
    }

    public function setViewEvent(ViewEvent $event): static
    {
        $this->viewEvent = $event;
        return $this;
    }

    /**
     * Lazy-loads a helper plugin manager if none available.
     */
    public function getHelperPluginManager(): ?HelperPluginManager
    {
        if ($this->helpers === null) {
            throw new \LogicException('The HAL helper plugin manager must be injected before rendering.');
        }

        return $this->helpers;
    }

    /**
     * @return ViewEvent|null
     */
    public function getViewEvent(): ?ViewEvent
    {
        return $this->viewEvent;
    }

    /**
     * Render a view model
     *
     * If the view model is a HalJsonRenderer, determines if it represents
     * a Collection or Entity, and, if so, creates a custom
     * representation appropriate to the type.
     *
     * If not, it passes control to the parent to render.
     *
     * @param mixed $nameOrModel
     * @param iterable<non-empty-string, mixed>|null $values
     * @return string
     */
    #[Override]
    public function render($nameOrModel, $values = null): string
    {
        if (!$nameOrModel instanceof HalJsonModel) {
            /** @psalm-var ModelInterface|string $nameOrModel */
            return parent::render(templateNameOrModel: $nameOrModel, variables: $values);
        }

        if ($nameOrModel->isEntity()) {
            /** @psalm-var Hal $helper */
            $helper = \Jield\Stdlib\Value::present($this->getHelperPluginManager())->get('Hal');
            /** @psalm-var Entity $entity */
            $entity = $nameOrModel->getPayload();
            $payload = $helper->renderEntity(halEntity: $entity);
            /** @psalm-suppress InvalidArgument */
            return parent::render(templateNameOrModel: $payload);
        }

        if ($nameOrModel->isCollection()) {
            /** @var Hal $helper */
            $helper = \Jield\Stdlib\Value::present($this->getHelperPluginManager())->get('Hal');
            /** @var Collection $collection */
            $collection = $nameOrModel->getPayload();
            $payload = $helper->renderCollection(halCollection: $collection);

            if ($payload instanceof ApiProblem) {
                return $this->renderApiProblem(problem: $payload);
            }

            /** @psalm-suppress InvalidArgument to be discussed */
            return parent::render(templateNameOrModel: $payload);
        }

        return parent::render(templateNameOrModel: $nameOrModel, variables: $values);
    }

    /**
     * Render an API-Problem result
     *
     * Creates an ApiProblemModel with the provided ApiProblem, and passes it
     * on to the composed ApiProblemRenderer to render.
     *
     * If a ViewEvent is composed, it passes the ApiProblemModel to it so that
     * the ApiProblemStrategy can be invoked when populating the response.
     *
     */
    protected function renderApiProblem(ApiProblem $problem): string
    {
        $model = new ApiProblemModel(problem: $problem);
        $event = $this->getViewEvent();
        if ($event instanceof ViewEvent) {
            $event->setResult($model);
        }

        return $this->apiProblemRenderer->render($model);
    }
}
