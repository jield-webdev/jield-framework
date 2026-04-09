<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use Traversable;

interface MessageInterface
{
    /**
     * Set metadata
     *
     * @param Traversable|array|int|string $spec
     * @param mixed|null $value
     * @return $this
     */
    public function setMetadata(Traversable|array|int|string $spec, mixed $value = null): static;

    /**
     * Get metadata
     *
     * @param int|string|null $key
     * @return mixed
     */
    public function getMetadata(int|string|null $key = null): mixed;

    /**
     * Set content
     *
     * @param  mixed $content
     * @return mixed
     */
    public function setContent(mixed $content): mixed;

    /**
     * Get content
     *
     * @return mixed
     */
    public function getContent(): mixed;
}
