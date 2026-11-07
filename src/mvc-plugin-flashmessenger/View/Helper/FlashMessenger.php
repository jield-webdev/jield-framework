<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\FlashMessenger\View\Helper;

use Jield\Mvc\Plugin\FlashMessenger\FlashMessenger as PluginFlashMessenger;
use Jield\Mvc\View\Helper\AbstractTranslatorHelper;
use Jield\Stdlib\Value;
use Laminas\View\Exception\InvalidArgumentException;
use Laminas\View\Helper\EscapeHtml;
use Webmozart\Assert\Assert;

use function array_walk_recursive;
use function assert;
use function call_user_func_array;
use function implode;
use function sprintf;

/**
 * Helper to proxy the plugin flash messenger
 * Duck-types against Laminas\I18n\Translator\TranslatorAwareInterface.
 *
 * @method PluginFlashMessenger addMessage(string $string)
 * @method PluginFlashMessenger addInfoMessage(string $string)
 * @method PluginFlashMessenger addSuccessMessage(string $string)
 * @method PluginFlashMessenger addErrorMessage(string $string)
 * @method PluginFlashMessenger addWarningMessage(string $string)
 * @method bool hasMessages(?string $namespace = null)
 * @method bool hasInfoMessages()
 * @method bool hasSuccessMessages()
 * @method bool hasErrorMessages()
 * @method bool hasWarningMessages()
 * @method bool hasCurrentMessages(?string $namespace = null)
 * @method bool hasCurrentInfoMessages()
 * @method bool hasCurrentSuccessMessages()
 * @method bool hasCurrentErrorMessages()
 * @method bool hasCurrentWarningMessages()
 */
// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:too-many-methods(1)
class FlashMessenger extends AbstractTranslatorHelper
{
    /**
     * Default attributes for the open format tag
     *
     * @var array<string, string>
     */
    protected array $classMessages = [
        'info' => PluginFlashMessenger::NAMESPACE_INFO,
        'error' => PluginFlashMessenger::NAMESPACE_ERROR,
        'success' => PluginFlashMessenger::NAMESPACE_SUCCESS,
        'default' => PluginFlashMessenger::NAMESPACE_DEFAULT,
        'warning' => PluginFlashMessenger::NAMESPACE_WARNING,
    ];

    protected string $messageCloseString = '</li></ul>';
    protected string $messageOpenFormat = '<ul%s><li>';
    protected string $messageSeparatorString = '</li><li>';

    /**
     * Flag whether to escape messages
     */
    protected bool $autoEscape = true;

    /**
     * Html escape helper
     */
    protected ?EscapeHtml $escapeHtmlHelper = null;

    /**
     * Flash messenger plugin
     */
    protected ?PluginFlashMessenger $pluginFlashMessenger = null;

    /**
     * All namespaces of FlashMessenger
     *
     * Keys of array are the namespace names
     *
     * @var array<string, FlashMessengerNamespace> $namespaces
     */
    private array $namespaces = [];

    /**
     * @param array<string, FlashMessengerNamespace> $namespaces
     */
    public function __construct(array $namespaces = [])
    {
        foreach ($namespaces as $namespace) {
            $this->namespaces[$namespace->getName()] = $namespace;
        }
    }

    /**
     * Returns the flash messenger plugin controller
     *
     * @return FlashMessenger|PluginFlashMessenger|array<array-key, string>
     */
    public function __invoke(?string $namespace = null)
    {
        if (null === $namespace) {
            return $this;
        }
        $flashMessenger = $this->getPluginFlashMessenger();

        return $flashMessenger->getMessagesFromNamespace($namespace);
    }

    /**
     * Get the flash messenger plugin
     *
     * @return PluginFlashMessenger
     */
    public function getPluginFlashMessenger()
    {
        if (null === $this->pluginFlashMessenger) {
            $this->setPluginFlashMessenger(new PluginFlashMessenger());
        }
        assert(
            $this->pluginFlashMessenger instanceof PluginFlashMessenger,
            'Expected $this->pluginFlashMessenger instanceof PluginFlashMessenger.',
        );

        return $this->pluginFlashMessenger;
    }

    /**
     * Set the flash messenger plugin
     *
     * @return FlashMessenger
     * @throws InvalidArgumentException For an invalid $pluginFlashMessenger.
     */
    public function setPluginFlashMessenger(PluginFlashMessenger $pluginFlashMessenger)
    {
        $this->pluginFlashMessenger = $pluginFlashMessenger;
        return $this;
    }

    /**
     * Proxy the flash messenger plugin controller
     *
     * @return mixed
     */
    public function __call(string $method, array $argv)
    {
        $flashMessenger = $this->getPluginFlashMessenger();
        return call_user_func_array(Assert::isCallable([$flashMessenger, $method]), $argv);
    }

    /**
     * Render Messages
     *
     * @param array<array-key, string> $classes
     * @return string
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function render(string $namespace = 'default', array $classes = [], ?bool $autoEscape = null)
    {
        $flashMessenger = $this->getPluginFlashMessenger();
        $messages = $flashMessenger->getMessagesFromNamespace($namespace);
        return $this->renderMessages($namespace, $messages, $classes, $autoEscape);
    }

    /**
     * Render Messages
     *
     * @param array<array-key, string> $messages
     * @param array<array-key, string> $classes
     * @return string
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function renderMessages(
        string $namespace = 'default',
        array $messages = [],
        array $classes = [],
        ?bool $autoEscape = null,
    ) {
        if ($messages === []) {
            return '';
        }

        // Prepare classes for opening tag
        if ($classes === []) {
            $classes = [$this->getClasses($namespace)];
        }

        $autoEscape ??= $this->autoEscape;

        // Flatten message array
        $escapeHtml = $this->getEscapeHtmlHelper();
        $messagesToPrint = [];
        $translator = $this->getTranslator();
        $translatorTextDomain = $this->getTranslatorTextDomain();
        array_walk_recursive(
            $messages,
            /** @throws \ReflectionException */ static function ($item) use (
                &$messagesToPrint,
                $escapeHtml,
                $autoEscape,
                $translator,
                $translatorTextDomain,
            ): void {
                if ($translator !== null) {
                    /** @mago-var mixed $item */
                    $item = Value::call($translator, 'translate', [$item, $translatorTextDomain]);
                }

                if ($autoEscape) {
                    $messagesToPrint[] = $escapeHtml($item);
                    return;
                }

                $messagesToPrint[] = $item;
            },
        );

        if ($messagesToPrint === []) {
            return '';
        }

        // Generate markup
        $markup = sprintf($this->getMessageOpenFormat($namespace), ' class="' . implode(' ', $classes) . '"');
        $markup .= implode(
            sprintf($this->getMessageSeparatorString($namespace), ' class="' . implode(' ', $classes) . '"'),
            Value::scalars($messagesToPrint),
        );
        $markup .= $this->getMessageCloseString($namespace);
        return $markup;
    }

    private function getClasses(string $namespaceName): string
    {
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            return $this->classMessages[$namespaceName] ?? '';
        }

        return $namespace->getClasses();
    }

    private function getNamespace(string $namespace): ?FlashMessengerNamespace
    {
        return $this->namespaces[$namespace] ?? null;
    }

    /**
     * Retrieve the escapeHtml helper
     *
     * @return EscapeHtml
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getEscapeHtmlHelper()
    {
        $this->escapeHtmlHelper ??= $this->getView()->plugin('escapehtml', EscapeHtml::class);

        return $this->escapeHtmlHelper;
    }

    /**
     * Get the formatted string used to open message representation
     *
     * @return string
     */
    public function getMessageOpenFormat(?string $namespaceName = null)
    {
        $namespaceName ??= $this->getDefaultNamespaceName();
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            return $this->messageOpenFormat;
        }

        return $namespace->getMessageOpenFormat();
    }

    /**
     * Set the formatted string used to open message representation
     *
     * @return FlashMessenger
     */
    public function setMessageOpenFormat(string $messageOpenFormat, ?string $namespaceName = null)
    {
        $namespaceName ??= $this->getDefaultNamespaceName();
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            $this->messageOpenFormat = $messageOpenFormat;
            return $this;
        }
        $namespace->setMessageOpenFormat($messageOpenFormat);
        return $this;
    }

    /**
     * Returns default namespace name
     */
    private function getDefaultNamespaceName(): string
    {
        return PluginFlashMessenger::NAMESPACE_DEFAULT;
    }

    /**
     * Get the string used to separate messages
     *
     * @return string
     */
    public function getMessageSeparatorString(?string $namespaceName = null)
    {
        $namespaceName ??= $this->getDefaultNamespaceName();
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            return $this->messageSeparatorString;
        }

        return $namespace->getMessageSeparatorString();
    }

    /**
     * Set the string used to separate messages
     *
     * @return FlashMessenger
     */
    public function setMessageSeparatorString(string $messageSeparatorString, ?string $namespaceName = null)
    {
        $namespaceName ??= $this->getDefaultNamespaceName();
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            $this->messageSeparatorString = $messageSeparatorString;
            return $this;
        }
        $namespace->setMessageSeparatorString($messageSeparatorString);
        return $this;
    }

    /**
     * Get the string used to close message representation
     *
     * @return string
     */
    public function getMessageCloseString(?string $namespaceName = null)
    {
        $namespaceName ??= $this->getDefaultNamespaceName();
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            return $this->messageCloseString;
        }

        return $namespace->getMessageCloseString();
    }

    /**
     * Set the string used to close message representation
     *
     * @return FlashMessenger
     */
    public function setMessageCloseString(string $messageCloseString, ?string $namespaceName = null)
    {
        $namespaceName ??= $this->getDefaultNamespaceName();
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            $this->messageCloseString = $messageCloseString;
            return $this;
        }
        $namespace->setMessageCloseString($messageCloseString);

        return $this;
    }

    /**
     * Render Current Messages
     *
     * @return string
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function renderCurrent(string $namespace = 'default', array $classes = [], ?bool $autoEscape = null)
    {
        $flashMessenger = $this->getPluginFlashMessenger();
        $messages = $flashMessenger->getCurrentMessagesFromNamespace($namespace);
        return $this->renderMessages($namespace, $messages, Value::strings($classes), $autoEscape);
    }

    /**
     * Return whether auto escaping is enabled or disabled
     *
     * @return bool
     */
    public function getAutoEscape()
    {
        return $this->autoEscape;
    }

    /**
     * Set whether or not auto escaping should be used
     *
     * @return self
     */
    public function setAutoEscape(bool $autoEscape = true)
    {
        $this->autoEscape = $autoEscape;
        return $this;
    }
}
