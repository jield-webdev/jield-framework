<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Jield\ApiTools\Hal\Collection as HalCollection;
use Jield\ApiTools\Hal\Entity as HalEntity;
use JsonSerializable;
use Jield\Json\Json;
use Laminas\Stdlib\JsonSerializable as StdlibJsonSerializable;
use Laminas\View\Model\JsonModel as BaseJsonModel;
use Override;
use function json_last_error;
use const JSON_ERROR_CTRL_CHAR;
use const JSON_ERROR_DEPTH;
use const JSON_ERROR_NONE;
use const JSON_ERROR_STATE_MISMATCH;
use const JSON_ERROR_UTF8;

class JsonModel extends BaseJsonModel
{
    /**
     * Mark view model as terminal by default (intended for use with APIs)
     *
     * @var bool
     */
    protected bool $terminate = true;

    /**
     * Set variables
     *
     * Overrides parent to extract variables from JsonSerializable objects.
     *
     * @param \Traversable|array|JsonSerializable|StdlibJsonSerializable $variables
     * @param bool $overwrite
     * @return self
     */
    #[Override]
    public function setVariables(\Traversable|array|StdlibJsonSerializable|JsonSerializable $variables, bool $overwrite = false): JsonModel
    {
        if ($variables instanceof JsonSerializable) {
            $variables = $variables->jsonSerialize();
        }

        return parent::setVariables(variables: $variables, overwrite: $overwrite);
    }

    /**
     * Override setTerminal()
     *
     * Becomes a no-op; this model should always be terminal.
     *
     * @param bool $flag
     * @return static
     */
    #[Override]
    public function setTerminal(bool $flag): static
    {
        // Do nothing; should always terminate
        return $this;
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
    #[Override]
    public function serialize(): false|string
    {
        $variables = $this->getVariables();

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
