<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use Traversable;
use function array_key_exists;
use function get_debug_type;
use function is_array;
use function is_scalar;
use function sprintf;

class Message implements MessageInterface
{
    /** @var array */
    protected array $metadata = [];

    /** @var mixed */
    protected mixed $content = '';

    /**
     * Set message metadata
     *
     * Non-destructive setting of message metadata; always adds to the metadata, never overwrites
     * the entire metadata container.
     *
     * @param Traversable|array|int|string $spec
     * @param mixed|null $value
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setMetadata(Traversable|array|int|string $spec, mixed $value = null): static
    {
        if (is_scalar(value: $spec)) {
            $this->metadata[$spec] = $value;
            return $this;
        }
        if (!is_array(value: $spec) && !$spec instanceof Traversable) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Expected a string, array, or Traversable argument in first position; received "%s"',
                get_debug_type(value: $spec)
            ));
        }
        foreach ($spec as $key => $value) {
            $this->metadata[$key] = $value;
        }
        return $this;
    }

    /**
     * Retrieve all metadata or a single metadatum as specified by key
     *
     * @param int|string|null $key
     * @return mixed
     * @throws Exception\InvalidArgumentException
     */
    public function getMetadata(int|string|null $key = null): mixed
    {
        if (null === $key) {
            return $this->metadata;
        }

        if (!is_scalar(value: $key)) {
            throw new Exception\InvalidArgumentException(message: 'Non-scalar argument provided for key');
        }

        if (array_key_exists(key: $key, array: $this->metadata)) {
            return $this->metadata[$key];
        }

        return $this->metadata;
    }

    /**
     * Set message content
     *
     * @param mixed $content
     * @return Message
     */
    public function setContent(mixed $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get message content
     *
     * @return mixed
     */
    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        $request = '';
        foreach ($this->getMetadata() as $key => $value) {
            $request .= sprintf(
                "%s: %s\r\n",
                (string)$key,
                (string)$value
            );
        }
        $request .= "\r\n" . $this->getContent();
        return $request;
    }
}
