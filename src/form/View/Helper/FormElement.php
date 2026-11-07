<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\Element;
use Jield\Form\ElementInterface;
use Laminas\View\HelperPluginManagerInterface;

use function assert;
use function is_callable;

class FormElement extends AbstractHelper
{
    public const string DEFAULT_HELPER = 'forminput';

    /**
     * Instance map to view helper
     *
     * @var array<class-string<ElementInterface>, string>
     */
    protected array $classMap = [
        Element\Button::class => 'formbutton',
        Element\Csrf::class => 'formhidden',
        Element\Collection::class => 'formcollection',
        Element\CollectionInterface::class => 'formcollection',
        Element\DateTimeSelect::class => 'formdatetimeselect',
        Element\DateSelect::class => 'formdateselect',
        Element\MonthSelect::class => 'formmonthselect',
    ];

    /**
     * Type map to view helper
     *
     * @var array<string, string>
     */
    protected array $typeMap = [
        'checkbox' => 'formcheckbox',
        'color' => 'formcolor',
        'date' => 'formdate',
        'datetime' => 'formdatetime',
        'datetime-local' => 'formdatetimelocal',
        'email' => 'formemail',
        'file' => 'formfile',
        'hidden' => 'formhidden',
        'image' => 'formimage',
        'month' => 'formmonth',
        'multi_checkbox' => 'formmulticheckbox',
        'number' => 'formnumber',
        // This is a public protocol or form identifier, not a credential.
        // @mago-expect lint:no-literal-password(1)
        'password' => 'formpassword',
        'radio' => 'formradio',
        'range' => 'formrange',
        'reset' => 'formreset',
        'search' => 'formsearch',
        'select' => 'formselect',
        'submit' => 'formsubmit',
        'tel' => 'formtel',
        'text' => 'formtext',
        'textarea' => 'formtextarea',
        'time' => 'formtime',
        'url' => 'formurl',
        'week' => 'formweek',
    ];

    /**
     * Default helper name
     */
    protected string $defaultHelper = self::DEFAULT_HELPER;

    public function __construct(
        private readonly HelperPluginManagerInterface $helpers,
    ) {
    }

    /**
     * Invoke helper as function
     *
     * Proxies to {@link render()}.
     *
     * @template T as null|ElementInterface
     * @psalm-param T $element
     * @psalm-return (T is null ? static : string)
     * @throws \Laminas\ServiceManager\Exception\InvalidServiceException
     */
    public function __invoke(?ElementInterface $element = null): string|static
    {
        if (!$element) {
            return $this;
        }

        return $this->render(element: $element);
    }

    /**
     * Render an element
     *
     * Introspects the element type and attributes to determine which
     * helper to utilize when rendering.
     *
     * @throws \Laminas\ServiceManager\Exception\InvalidServiceException
     */
    public function render(ElementInterface $element): string
    {
        $renderedInstance = $this->renderInstance(element: $element);

        if ($renderedInstance !== null) {
            return $renderedInstance;
        }

        $renderedType = $this->renderType(element: $element);

        if ($renderedType !== null) {
            return $renderedType;
        }

        return $this->renderHelper(name: $this->defaultHelper, element: $element);
    }

    /**
     * Set default helper name
     */
    public function setDefaultHelper(string $name): static
    {
        $this->defaultHelper = $name;

        return $this;
    }

    /**
     * Add form element type to plugin map
     */
    public function addType(string $type, string $plugin): static
    {
        $this->typeMap[$type] = $plugin;

        return $this;
    }

    /**
     * Add instance class to plugin map
     *
     * @param class-string<ElementInterface> $class
     */
    public function addClass(string $class, string $plugin): static
    {
        $this->classMap[$class] = $plugin;

        return $this;
    }

    /**
     * Render element by helper name
     *
     * @throws \Laminas\ServiceManager\Exception\InvalidServiceException
     */
    protected function renderHelper(string $name, ElementInterface $element): string
    {
        /** @mago-var mixed $helper */
        $helper = $this->helpers->get($name);
        assert(assertion: is_callable(value: $helper), description: 'Expected is_callable($helper).');
        return \Webmozart\Assert\Assert::string($helper($element));
    }

    /**
     * Render element by instance map
     *
     * @throws \Laminas\ServiceManager\Exception\InvalidServiceException
     */
    protected function renderInstance(ElementInterface $element): ?string
    {
        foreach ($this->classMap as $class => $pluginName) {
            if ($element instanceof $class) {
                return $this->renderHelper(name: $pluginName, element: $element);
            }
        }

        return null;
    }

    /**
     * Render element by type map
     *
     * @throws \Laminas\ServiceManager\Exception\InvalidServiceException
     */
    protected function renderType(ElementInterface $element): ?string
    {
        $type = $element->getAttribute(key: 'type');

        if (($this->typeMap[(string) $type] ?? null) !== null) {
            return $this->renderHelper(name: $this->typeMap[(string) $type], element: $element);
        }

        return null;
    }
}
