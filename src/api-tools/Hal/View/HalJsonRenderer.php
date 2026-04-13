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
use function json_encode;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Handles rendering of the following:
 *
 * - API-Problem
 * - HAL collections
 * - HAL resources
 */
class HalJsonRenderer
{
    protected ?HelperPluginManager $helpers = null;

    protected string $lastContentType = 'application/json';

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

    public function getLastContentType(): string
    {
        return $this->lastContentType;
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
    public function render(mixed $nameOrModel, ArrayAccess|array|null $values = null): string
    {
        if (!$nameOrModel instanceof HalJsonModel) {
            if ($nameOrModel instanceof ModelInterface) {
                return $this->encodePayload(payload: $nameOrModel->getVariables());
            }

            return $this->encodePayload(payload: $nameOrModel);
        }

        if ($nameOrModel->isEntity()) {
            /** @psalm-var Hal $helper */
            $helper = $this->getHelperPluginManager()->get(name: 'Hal');
            /** @psalm-var Entity $entity */
            $entity  = $nameOrModel->getPayload();
            $payload = $helper->renderEntity(halEntity: $entity);
            $this->lastContentType = 'application/hal+json';
            return $this->encodePayload(payload: $payload);
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

            $this->lastContentType = 'application/hal+json';
            return $this->encodePayload(payload: $payload);
        }

        $this->lastContentType = 'application/json';
        return $this->encodePayload(payload: $nameOrModel);
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
        $this->lastContentType = ApiProblem::CONTENT_TYPE;
        $model = new ApiProblemModel(problem: $problem);
        return $this->apiProblemRenderer->render(nameOrModel: $model);
    }

    private function encodePayload(mixed $payload): string
    {
        return json_encode(
            value: $payload,
            flags: JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }
}
