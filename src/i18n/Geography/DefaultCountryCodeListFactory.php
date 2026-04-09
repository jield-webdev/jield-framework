<?php

declare(strict_types=1);

namespace Jield\I18n\Geography;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class DefaultCountryCodeListFactory
{
    public function __invoke(): DefaultCountryCodeList
    {
        return DefaultCountryCodeList::create();
    }
}
