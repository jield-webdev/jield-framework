<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\View;

use Jield\ApiTools\ApiProblem\View\ApiProblemModel;
use Laminas\View\Model\ModelInterface;

/**
 * Helper to render HAL JSON responses and determine content type.
 *
 * This will give the following content types:
 *
 * - application/hal+json for a result that contains HAL-compliant links
 * - application/json for all other responses
 */
class HalJsonStrategy
{
    /** @var string */
    protected string $contentType = 'application/json';

    private HalJsonRenderer $renderer;

    public function __construct(HalJsonRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function render(HalJsonModel $model): string
    {
        return $this->renderer->render(nameOrModel: $model);
    }

    /**
     * Determine the response content-type to return based on the view model.
     *
     * @param ApiProblemModel|HalJsonModel|ModelInterface|null $model
     * @return string The content-type to use.
     */
    public function getContentTypeFromModel(HalJsonModel|ApiProblemModel|ModelInterface|null $model): string
    {
        if ($model instanceof ApiProblemModel) {
            return 'application/problem+json';
        }

        if (
            $model instanceof HalJsonModel
            && ($model->isCollection() || $model->isEntity())
        ) {
            return 'application/hal+json';
        }

        return $this->contentType;
    }
}
