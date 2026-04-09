<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\View;

use Jield\ApiTools\Hal\Collection;
use Jield\ApiTools\Hal\Entity;
use Laminas\View\Model\JsonModel;

use Override;
use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Simple extension to facilitate the specialized JsonStrategy and JsonRenderer
 * in this Module.
 */
class HalJsonModel extends JsonModel
{
    /** @var bool */
    protected bool $terminate = true;

    /**
     * Does the payload represent a HAL collection?
     *
     * @return bool
     */
    public function isCollection(): bool
    {
        /** @var mixed $payload */
        $payload = $this->getPayload();
        return $payload instanceof Collection;
    }

    /**
     * Does the payload represent a HAL item?
     *
     * Deprecated; please use isEntity().
     *
     * @deprecated
     *
     * @return bool
     */
    public function isResource(): bool
    {
        trigger_error(message: sprintf('%s is deprecated; please use %s::isEntity', __METHOD__, static::class), error_level: E_USER_DEPRECATED);
        return static::isEntity();
    }

    /**
     * Does the payload represent a HAL entity?
     *
     * @return bool
     */
    public function isEntity(): bool
    {
        /** @var mixed $payload */
        $payload = $this->getPayload();
        return $payload instanceof Entity;
    }

    /**
     * Set the payload for the response
     *
     * This is the value to represent in the response.
     *
     */
    public function setPayload(mixed $payload): static
    {
        $this->setVariable(name: 'payload', value: $payload);
        return $this;
    }

    /**
     * Retrieve the payload for the response
     *
     * @return mixed
     */
    public function getPayload(): mixed
    {
        return $this->getVariable(name: 'payload');
    }

    /**
     * Override setTerminal()
     *
     * Does nothing; does not allow re-setting "terminate" terminate.
     *
     * @param bool $terminate
     * @return static
     */
    #[Override]
    public function setTerminal(bool $terminate = true): static
    {
        return $this;
    }
}
