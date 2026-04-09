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
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Renderer\JsonRenderer;
use Laminas\View\ViewEvent;
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

    public function __construct(protected ApiProblemRenderer $apiProblemRenderer)
    {
    }

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
        if (!$this->helpers instanceof HelperPluginManager) {
            $this->setHelperPluginManager(helpers: $helpers = new HelperPluginManager());
            return $helpers;
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
     * @param ArrayAccess|array|null $values
     * @return string
     */
    #[Override]
    public function render(mixed $nameOrModel, ArrayAccess|array|null $values = null): string
    {
        if (!$nameOrModel instanceof HalJsonModel) {
            /** @psalm-var ModelInterface|string $nameOrModel */
            return parent::render(nameOrModel: $nameOrModel, values: $values);
        }

        if ($nameOrModel->isEntity()) {
            /** @psalm-var Hal $helper */
            $helper = $this->getHelperPluginManager()->get(name: 'Hal');
            /** @psalm-var Entity $entity */
            $entity  = $nameOrModel->getPayload();
            $payload = $helper->renderEntity(halEntity: $entity);
            /** @psalm-suppress InvalidArgument */
            return parent::render(nameOrModel: $payload);
        }

        if ($nameOrModel->isCollection()) {
            /** @var Hal $helper */
            $helper = $this->getHelperPluginManager()->get(name: 'Hal');
            /** @var Collection $collection */
            $collection = $nameOrModel->getPayload();
            $payload    = $helper->renderCollection(halCollection: $collection);

            if ($payload instanceof ApiProblem) {
                return $this->renderApiProblem(problem: $payload);
            }

            /** @psalm-suppress InvalidArgument to be discussed */
            return parent::render(nameOrModel: $payload);
        }

        return parent::render(nameOrModel: $nameOrModel, values: $values);
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
            $event->setModel(model: $model);
        }

        return $this->apiProblemRenderer->render(nameOrModel: $model);
    }
}
