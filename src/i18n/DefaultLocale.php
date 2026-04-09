<?php

declare(strict_types=1);

namespace Jield\I18n;

use Stringable;

final readonly class DefaultLocale implements Stringable
{
    /** @param non-empty-string $locale */
    public function __construct(public string $locale)
    {
    }

    /** @return non-empty-string */
    public function __toString(): string
    {
        return $this->locale;
    }
}
