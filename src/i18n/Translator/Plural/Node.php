<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Plural;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class Node
{
    /** @param array{0?:int|self, 1?: self, 2?: self} $arguments */
    public function __construct(
        public string $id,
        public array $arguments,
    ) {
    }
}
