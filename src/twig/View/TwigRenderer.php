<?php

declare(strict_types=1);

namespace Jield\Twig\View;

use Laminas\View\Exception;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Renderer\RendererInterface;
use Laminas\View\View;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader;
use function call_user_func_array;
use function is_callable;
use function sprintf;

class TwigRenderer implements RendererInterface
{
    protected bool $canRenderTrees = true;

    protected HelperPluginManager $helperPluginManager;

    /** @var array Cache for the plugin call */
    private array $pluginCache = [];

    public function __construct(
        protected View               $view,
        protected Loader\ChainLoader $loader,
        protected Environment        $environment,
        protected TwigResolver       $resolver
    )
    {
    }

    /**
     * Overloading: proxy to helpers
     *
     * Proxies to the attached plugin manager to retrieve, return, and potentially
     * execute helpers.
     *
     * * If the helper does not define __invoke, it will be returned
     * * If the helper does define __invoke, it will be called as a functor
     *
     * @param array $argv
     * @return mixed
     */
    public function __call(string $method, array $argv)
    {
        if (!isset($this->pluginCache[$method])) {
            $this->pluginCache[$method] = $this->plugin($method);
        }

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
     * @param null|array $options Options to pass to plugin constructor (if not already instantiated)
     */
    public function plugin(string $name, ?array $options = null)
    {
        $helper = $this->getHelperPluginManager()->setRenderer($this);

        if ($helper->has($name)) {
            return $helper->get($name, $options);
        }

        return $this->getHelperPluginManager()->get($name, $options);
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
    public function setHelperPluginManager(HelperPluginManager $helperPluginManager): self
    {
        $helperPluginManager->setRenderer($this);
        $this->helperPluginManager = $helperPluginManager;
        return $this;
    }

    public function getHelperPluginManager(): HelperPluginManager
    {
        return $this->helperPluginManager;
    }

    /**
     * @param string|ModelInterface $templateNameOrModel
     * @param $variables
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(string|ModelInterface $templateNameOrModel, $variables = null): string
    {
        $model = null;

        if ($templateNameOrModel instanceof ModelInterface) {
            $model               = $templateNameOrModel;
            $templateNameOrModel = $model->getTemplate();

            if (empty($templateNameOrModel)) {
                throw new Exception\DomainException(sprintf(
                    '%s: received View Model argument, but template is empty',
                    __METHOD__
                ));
            }

            $values = (array)$model->getVariables();
        }

        if (!$this->canRender($templateNameOrModel)) {
            return '';
        }

        if (null === $variables) {
            $variables = [];
        }

        if ($model && $this->canRenderTrees() && $model->hasChildren()) {
            $values['content'] = $values['content'] ?? '';
            foreach ($model as $child) {
                /** @var ModelInterface $child */
                if ($this->canRender($child->getTemplate())) {
                    $templateName = $this->resolver->resolve($child->getTemplate());
                    if ($templateName === false) {
                        continue;
                    }
                    $template = $this->environment->load($templateName);

                    $childValues = (array)$child->getVariables();
                    if ($child->hasChildren()) {
                        foreach ($child->getChildren() as $grandChild) {
                            /** @var ModelInterface $grandChild */
                            $childValues[$grandChild->captureTo()] = $this->render($grandChild);
                        }
                    }

                    return $template->render($childValues);
                }
                $child->setOption('has_parent', true);
                $values['content'] .= $this->view->render($child);
            }
        }

        $templateName = $this->resolver->resolve($templateNameOrModel);
        if ($templateName === false) {
            return '';
        }

        $template = $this->environment->load($templateName);
        return $template->render((array)$variables);
    }
}
