<?php

declare(strict_types=1);

namespace Jield\Twig\View;

use Jield\Stdlib\Value;
use Laminas\View\Exception;
use Laminas\View\HelperPluginManagerInterface;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Renderer\RendererInterface;
use Laminas\View\View;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader;
use Webmozart\Assert\Assert;

use function call_user_func_array;
use function is_callable;
use function sprintf;

class TwigRenderer implements RendererInterface
{
    protected bool $canRenderTrees = true;

    protected HelperPluginManagerInterface $helperPluginManager;

    /** @var array Cache for the plugin call */
    private array $pluginCache = [];

    public function __construct(
        protected View $view,
        protected Loader\ChainLoader $loader,
        protected Environment $environment,
        protected TwigResolver $resolver,
    ) {}

    /**
     * Overloading: proxy to helpers
     *
     * Proxies to the attached plugin manager to retrieve, return, and potentially
     * execute helpers.
     *
     * * If the helper does not define __invoke, it will be returned
     * * If the helper does define __invoke, it will be called as a functor
     *
     * @return mixed
     * @throws \Laminas\ServiceManager\Exception\InvalidServiceException
     */
    public function __call(string $method, array $argv)
    {
        $this->pluginCache[$method] ??= $this->plugin($method);

        if (is_callable($this->pluginCache[$method])) {
            return call_user_func_array($this->pluginCache[$method], $argv);
        }

        return $this->pluginCache[$method];
    }

    /**
     * @return $this
     */
    public function setCanRenderTrees(bool $canRenderTrees): self
    {
        $this->canRenderTrees = $canRenderTrees;
        return $this;
    }

    public function canRenderTrees(): bool
    {
        return $this->canRenderTrees;
    }

    /**
     * Get plugin instance, proxy to HelperPluginManager::get
     *
     * @param string $name Name of plugin to return
     * @throws \Laminas\ServiceManager\Exception\InvalidServiceException
     */
    public function plugin(string $name)
    {
        return $this->getHelperPluginManager()->get($name);
    }

    /**
     * Can the template be rendered?
     *
     * @see \Jield\Twig\Environment::canLoadTemplate()
     */
    public function canRender(string $name): bool
    {
        return $this->loader->exists($name);
    }

    /**
     * Return the template engine object, if any
     *
     * If using a third-party template engine, such as Smarty, patTemplate,
     * phplib, etc, return the template engine object. Useful for calling
     * methods on these objects, such as for setting filters, modifiers, etc.
     */
    public function getEngine(): Environment
    {
        return $this->environment;
    }

    /**
     * Set the resolver used to map a template name to a resource the renderer may consume.
     *
     * @return $this
     */
    public function setResolver(TwigResolver $resolver): self
    {
        $this->resolver = $resolver;
        return $this;
    }

    /**
     * @return $this
     */
    public function setHelperPluginManager(HelperPluginManagerInterface $helperPluginManager): self
    {
        $this->helperPluginManager = $helperPluginManager;
        return $this;
    }

    public function getHelperPluginManager(): HelperPluginManagerInterface
    {
        return $this->helperPluginManager;
    }

    /**
     * @param $variables
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception\RenderingFailedException
     * @throws Exception\DomainException
     */
    public function render(string|ModelInterface $templateNameOrModel, $variables = null): string
    {
        $variables ??= [];
        $values = is_array($variables) ? $variables : iterator_to_array($variables);
        if ($templateNameOrModel instanceof ModelInterface) {
            $model = $templateNameOrModel;
            $modelVariables = $model->getVariables();
            $values = [...$modelVariables, ...$values];
            foreach ($model->getChildren() as $child) {
                $capture = $child->captureTo();
                $content = $this->canRender($child->getTemplate())
                    ? $this->render($child)
                    : $this->view->render($child, enableLayout: false);
                $values[$capture] = $child->isAppend()
                    ? Value::stringify($values[$capture] ?? '') . $content
                    : $content;
            }
            $templateNameOrModel = $model->getTemplate();
        }
        $templateName = $this->resolver->resolve(Assert::stringNotEmpty($templateNameOrModel));
        if ($templateName === false) {
            throw new Exception\DomainException(sprintf('Cannot resolve template "%s"', $templateNameOrModel));
        }
        return $this->environment->render($templateName, $values);
    }
}
