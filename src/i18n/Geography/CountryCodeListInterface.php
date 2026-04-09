<?php

declare(strict_types=1);

namespace Jield\I18n\Geography;

use Countable;
use IteratorAggregate;
use Jield\I18n\CountryCode;

/**
 * @extends IteratorAggregate<array-key, CountryCode>
 */
interface CountryCodeListInterface extends IteratorAggregate, Countable
{
}
