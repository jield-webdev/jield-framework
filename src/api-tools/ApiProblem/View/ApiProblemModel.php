<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Jield\ApiTools\ApiProblem\ApiProblem;
use ArrayIterator;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;

class ApiProblemModel implements ModelInterface
{
    private ViewModel $model;

    /** @var ApiProblem */
    protected ApiProblem $problem;

    /** @var bool */
    protected bool $terminate = true;

    public function __construct(?ApiProblem $problem = null)
    {
        $this->model = new ViewModel();
        $this->model->setCaptureTo(capture: 'errors');
        $this->model->setTerminal(true);

        if ($problem instanceof ApiProblem) {
            $this->setApiProblem(problem: $problem);
        }
    }

    public function setApiProblem(ApiProblem $problem): static
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * @return ApiProblem
     */
    public function getApiProblem(): ApiProblem
    {
        return $this->problem;
    }

    public function getVariable(string $name, mixed $default = null): mixed
    {
        return $this->model->getVariable(name: $name, default: $default);
    }

    public function setVariable(string $name, mixed $value): static
    {
        $this->model->setVariable(name: $name, value: $value);
        return $this;
    }

    public function setVariables(iterable $variables, bool $overwrite = false): static
    {
        $this->model->setVariables(variables: $variables, overwrite: $overwrite);
        return $this;
    }

    public function getVariables(): array
    {
        return $this->model->getVariables();
    }

    public function setTemplate(string $template): static
    {
        $this->model->setTemplate(template: $template);
        return $this;
    }

    public function getTemplate(): string
    {
        return $this->model->getTemplate();
    }

    public function addChild(ModelInterface $child, string|null $captureTo = null, bool|null $append = null): static
    {
        $this->model->addChild(child: $child, captureTo: $captureTo, append: $append);
        return $this;
    }

    public function getChildren(): array
    {
        return $this->model->getChildren();
    }

    public function hasChildren(): bool
    {
        return $this->model->hasChildren();
    }

    public function setCaptureTo(string $capture): static
    {
        $this->model->setCaptureTo(capture: $capture);
        return $this;
    }

    public function captureTo(): string
    {
        return $this->model->captureTo();
    }

    public function setTerminal(bool $terminate): static
    {
        $this->model->setTerminal(terminate: $terminate);
        return $this;
    }

    public function terminate(): bool
    {
        return $this->model->terminate();
    }

    public function setAppend(bool $append): static
    {
        $this->model->setAppend(append: $append);
        return $this;
    }

    public function isAppend(): bool
    {
        return $this->model->isAppend();
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->model->getChildren());
    }
}
