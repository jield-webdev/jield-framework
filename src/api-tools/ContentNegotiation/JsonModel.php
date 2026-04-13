<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use ArrayIterator;
use Jield\ApiTools\Hal\Collection as HalCollection;
use Jield\ApiTools\Hal\Entity as HalEntity;
use JsonSerializable;
use Jield\Json\Json;
use Laminas\Stdlib\JsonSerializable as StdlibJsonSerializable;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use function json_last_error;
use const JSON_ERROR_CTRL_CHAR;
use const JSON_ERROR_DEPTH;
use const JSON_ERROR_NONE;
use const JSON_ERROR_STATE_MISMATCH;
use const JSON_ERROR_UTF8;

class JsonModel implements ModelInterface
{
    private ViewModel $model;

    /**
     * @var array
     */
    private array $options = [];

    /**
     * JSONP callback.
     *
     * @var string|null
     */
    protected ?string $jsonpCallback = null;

    /**
     * Mark view model as terminal by default (intended for use with APIs)
     *
     * @var bool
     */
    protected bool $terminate = true;

    public function __construct(iterable $variables = [], string $template = '', array $options = [])
    {
        $this->model   = new ViewModel($variables, $template);
        $this->options = $options;
        $this->model->setTerminal(true);
    }

    /**
     * Set variables
     *
     * Overrides parent to extract variables from JsonSerializable objects.
     *
     * @param \Traversable|array|JsonSerializable|StdlibJsonSerializable $variables
     * @param bool $overwrite
     * @return self
     */
    public function setVariables(
        \Traversable|array|StdlibJsonSerializable|JsonSerializable $variables,
        bool $overwrite = false
    ): JsonModel
    {
        if ($variables instanceof JsonSerializable) {
            $variables = $variables->jsonSerialize();
        }

        $this->model->setVariables(variables: $variables, overwrite: $overwrite);
        return $this;
    }

    /**
     * Override setTerminal()
     *
     * Becomes a no-op; this model should always be terminal.
     *
     * @param bool $flag
     * @return static
     */
    public function setTerminal(bool $flag): static
    {
        // Do nothing; should always terminate
        return $this;
    }

    /**
     * Set JSONP callback.
     *
     * @param string|null $callback
     * @return static
     */
    public function setJsonpCallback(?string $callback): static
    {
        $this->jsonpCallback = $callback;
        return $this;
    }

    /**
     * Get JSONP callback.
     *
     * @return string|null
     */
    public function getJsonpCallback(): ?string
    {
        return $this->jsonpCallback;
    }

    /**
     * Override serialize()
     *
     * Tests for the special top-level variable "payload", set by Jield\ApiTools\Rest\RestController.
     *
     * If discovered, the value is pulled and used as the variables to serialize.
     *
     * A further check is done to see if we have a Jield\ApiTools\Hal\Entity or
     * Jield\ApiTools\Hal\Collection, and, if so, we pull the top-level entity or
     * collection and serialize that.
     *
     * @return string
     */
    public function serialize(): false|string
    {
        $variables = $this->model->getVariables();

        // 'payload' == payload for HAL representations
        if (isset($variables['payload'])) {
            $variables = $variables['payload'];
        }

        // Use Jield\ApiTools\Hal\Entity's composed entity
        if ($variables instanceof HalEntity) {
            $variables = $variables->getEntity();
        }

        // Use Jield\ApiTools\Hal\Collection's composed collection
        if ($variables instanceof HalCollection) {
            $variables = $variables->getCollection();
        }

        if (null !== $this->jsonpCallback) {
            return $this->jsonpCallback . '(' . Json::encode(valueToEncode: $variables) . ');';
        }

        $serialized = Json::encode(valueToEncode: $variables);

        if (false === $serialized) {
            $this->raiseError(error: json_last_error());
        }

        return $serialized;
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

    public function setOptions(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Determine if an error needs to be raised; if so, throw an exception
     *
     * @param int $error One of the JSON_ERROR_* constants
     * @return never
     * @throws Exception\InvalidJsonException
     */
    protected function raiseError(int $error)
    {
        $message = 'JSON encoding error occurred: ';
        switch ($error) {
            case JSON_ERROR_NONE:
                return;
            case JSON_ERROR_DEPTH:
                $message .= 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $message .= 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message .= 'Unexpected control character found';
                break;
            case JSON_ERROR_UTF8:
                $message .= 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $message .= 'Unknown error';
                break;
        }

        throw new Exception\InvalidJsonException(message: $message);
    }
}
