<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\FlashMessenger\View\Helper;

use Jield\Mvc\Plugin\FlashMessenger\FlashMessenger as PluginFlashMessenger;
use Jield\Mvc\View\Helper\AbstractTranslatorHelper;
use Jield\Stdlib\Value;
use Laminas\Escaper\Escaper;
use Laminas\View\Exception\InvalidArgumentException;
use Laminas\View\Helper\EscapeHtml;

use function array_walk_recursive;
use function assert;
use function call_user_func_array;
use function gettype;
use function implode;
use function is_object;
use function method_exists;
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
    /** @var string */
    protected string $messageOpenFormat = '<ul%s><li>';
    /** @var string */
    protected string $messageSeparatorString = '</li><li>';

    /**
     * Flag whether to escape messages
     *
     * @var bool
     */
    protected bool $autoEscape = true;

    /**
     * Html escape helper
     *
     * @var EscapeHtml|null
     */
    protected ?EscapeHtml $escapeHtmlHelper = null;

    /**
     * Flash messenger plugin
     *
     * @var PluginFlashMessenger|null
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
     * @param string|null $namespace
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
        assert($this->pluginFlashMessenger instanceof PluginFlashMessenger);

        return $this->pluginFlashMessenger;
    }

    /**
     * Set the flash messenger plugin
     *
     * @param PluginFlashMessenger $pluginFlashMessenger
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
     * @param string $method
     * @param array $argv
     * @return mixed
     */
    public function __call(string $method, array $argv)
    {
        $flashMessenger = $this->getPluginFlashMessenger();
        return call_user_func_array(Value::callback([$flashMessenger, $method]), $argv);
    }

    /**
     * Render Messages
     *
     * @param string $namespace
     * @param array<array-key, string> $classes
     * @param bool|null $autoEscape
     * @return string
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
     * @param string $namespace
     * @param array<array-key, string> $messages
     * @param array<array-key, string> $classes
     * @param bool|null $autoEscape
     * @return string
     */
    protected function renderMessages(
        string $namespace = 'default',
        array $messages = [],
        array $classes = [],
        ?bool $autoEscape = null,
    ) {
        if (empty($messages)) {
            return '';
        }

        // Prepare classes for opening tag
        if (empty($classes)) {
            $classes = [$this->getClasses($namespace)];
        }

        $autoEscape ??= $this->autoEscape;

        // Flatten message array
        $escapeHtml = $this->getEscapeHtmlHelper();
        $messagesToPrint = [];
        /** @var mixed $translator */
        $translator = $this->getTranslator();
        /** @var mixed $translatorTextDomain */
        $translatorTextDomain = $this->getTranslatorTextDomain();
        array_walk_recursive($messages, static function ($item) use (
            &$messagesToPrint,
            $escapeHtml,
            $autoEscape,
            $translator,
            $translatorTextDomain,
        ): void {
            if ($translator !== null) {
                /** @var mixed $item */
                $item = Value::call($translator, 'translate', [$item, $translatorTextDomain]);
            }

            if ($autoEscape) {
                $messagesToPrint[] = $escapeHtml($item);
                return;
            }

            $messagesToPrint[] = $item;
        });

        if (empty($messagesToPrint)) {
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
     */
    protected function getEscapeHtmlHelper()
    {
        if ($this->escapeHtmlHelper) {
            return $this->escapeHtmlHelper;
        }

        /** @var mixed $view */
        $view = $this->getView();

        if (Value::truthy($view) && method_exists(Value::objectOrString($view), 'plugin')) {
            $plugin = Value::call($view, 'plugin', ['escapehtml']);
            assert($plugin instanceof EscapeHtml);
            $this->escapeHtmlHelper = $plugin;
        }

        if (!$this->escapeHtmlHelper instanceof EscapeHtml) {
            $this->escapeHtmlHelper = new EscapeHtml(new Escaper());
        }

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
     * @param string $messageOpenFormat
     * @return FlashMessenger
     */
    public function setMessageOpenFormat(string $messageOpenFormat, ?string $namespaceName = null)
    {
        $namespaceName ??= $this->getDefaultNamespaceName();
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            $this->messageOpenFormat = $messageOpenFormat;
        } else {
            $namespace->setMessageOpenFormat($messageOpenFormat);
        }
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
     * @param string $messageSeparatorString
     * @return FlashMessenger
     */
    public function setMessageSeparatorString(string $messageSeparatorString, ?string $namespaceName = null)
    {
        $namespaceName ??= $this->getDefaultNamespaceName();
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            $this->messageSeparatorString = $messageSeparatorString;
        } else {
            $namespace->setMessageSeparatorString($messageSeparatorString);
        }
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
     * @param string $messageCloseString
     * @return FlashMessenger
     */
    public function setMessageCloseString(string $messageCloseString, ?string $namespaceName = null)
    {
        $namespaceName ??= $this->getDefaultNamespaceName();
        $namespace = $this->getNamespace($namespaceName);
        if ($namespace === null) {
            $this->messageCloseString = $messageCloseString;
        } else {
            $namespace->setMessageCloseString($messageCloseString);
        }

        return $this;
    }

    /**
     * Render Current Messages
     *
     * @param string $namespace
     * @param bool|null $autoEscape
     * @return string
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
     * @param bool $autoEscape
     * @return self
     */
    public function setAutoEscape(bool $autoEscape = true)
    {
        $this->autoEscape = $autoEscape;
        return $this;
    }
}
