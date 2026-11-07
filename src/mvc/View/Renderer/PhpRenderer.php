<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Renderer;

use Jield\Stdlib\Service;
use Laminas\View\Helper\HeadLink;
use Laminas\View\Helper\HeadScript;
use Laminas\View\HelperPluginManagerInterface;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Renderer\PhpRenderer as NativeRenderer;
use Laminas\View\Renderer\RendererInterface;

final readonly class PhpRenderer implements RendererInterface
{
    public function __construct(
        private NativeRenderer $renderer,
        private HelperPluginManagerInterface $plugins,
    ) {}

    public function render(string|ModelInterface $templateNameOrModel, ?iterable $variables = null): string
    {
        return $this->renderer->render($templateNameOrModel, $variables);
    }

    /**
     * @template T of object
     * @param class-string<T>|null $type
     * @return ($type is null ? mixed : T)
     */
    public function plugin(string $name, ?string $type = null): mixed
    {
        if ($type !== null) {
            return Service::get($this->plugins, $type, $name);
        }

        return $this->plugins->get($name);
    }

    public function headLink(): HeadLink
    {
        return $this->plugin('headLink', HeadLink::class);
    }

    public function headScript(): HeadScript
    {
        return $this->plugin('headScript', HeadScript::class);
    }

    public function getHelperPluginManager(): HelperPluginManagerInterface
    {
        return $this->plugins;
    }

    public function __call(string $name, array $arguments): mixed
    {
        /** @var mixed $helper */
        $helper = $this->plugin($name);
        return is_callable($helper) ? $helper(...$arguments) : $helper;
    }

    /** @param callable(string): string $filter */
    public function setFilter(callable $filter): static
    {
        $this->renderer->setFilter($filter);
        return $this;
    }
}
