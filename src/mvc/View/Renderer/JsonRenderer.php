<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Renderer;

use Jield\Stdlib\Value;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Renderer\RendererInterface;

class JsonRenderer implements RendererInterface
{
    public function getEngine(): self
    {
        return $this;
    }

    /** @param mixed $templateNameOrModel JSON payload or view model. */
    public function render($templateNameOrModel, $variables = null): string
    {
        if (is_object($templateNameOrModel) && method_exists($templateNameOrModel, 'serialize')) {
            return Value::string($templateNameOrModel->serialize());
        }
        return json_encode(
            $templateNameOrModel instanceof ModelInterface
                ? $templateNameOrModel->getVariables()
                : $templateNameOrModel,
            JSON_THROW_ON_ERROR,
        );
    }
}
