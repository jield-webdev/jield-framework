<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Jield\ApiTools\Hal\Collection as HalCollection;
use Jield\ApiTools\Hal\Entity as HalEntity;
use Jield\Mvc\View\Model\JsonModel as BaseJsonModel;
use Jield\Stdlib\Json;
use JsonSerializable;
use Override;
use Traversable;

use const JSON_ERROR_CTRL_CHAR;
use const JSON_ERROR_DEPTH;
use const JSON_ERROR_NONE;
use const JSON_ERROR_STATE_MISMATCH;
use const JSON_ERROR_UTF8;

class JsonModel extends BaseJsonModel
{
    /**
     * Set variables
     *
     * Overrides parent to extract variables from JsonSerializable objects.
     *
     * @param array|Traversable|JsonSerializable $variables
     * @param bool $overwrite
     */
    #[Override]
    public function setVariables($variables, $overwrite = false): static
    {
        if ($variables instanceof JsonSerializable) {
            /** @mago-var mixed $variables */
            $variables = $variables->jsonSerialize();
        }

        return parent::setVariables(variables: \Jield\Stdlib\Value::namedIterable($variables), overwrite: $overwrite);
    }

    /**
     * Override setTerminal()
     *
     * Becomes a no-op; this model should always be terminal.
     *
     * @param bool $terminate
     */
    #[Override]
    public function setTerminal($terminate): static
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
     */
    #[Override]
    public function serialize(): string
    {
        $variables = $this->getVariables();

        // 'payload' == payload for HAL representations
        if (isset($variables['payload'])) {
            /** @mago-var mixed $variables */
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

        return $serialized;
    }

    /**
     * Determine if an error needs to be raised; if so, throw an exception
     *
     * @param int $error One of the JSON_ERROR_* constants
     * @return void
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
