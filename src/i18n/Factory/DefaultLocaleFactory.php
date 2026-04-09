<?php

declare(strict_types=1);

namespace Jield\I18n\Factory;

use Jield\I18n\DefaultLocale;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Locale;
use Psr\Container\ContainerInterface;

use function assert;
use function is_iterable;
use function is_string;
use function iterator_to_array;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class DefaultLocaleFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): DefaultLocale {
        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        /** @psalm-var mixed $locale */
        $locale = $config['locale'] ?? null;
        $locale = is_string(value: $locale) && $locale !== '' ? $locale : Locale::getDefault();
        assert(assertion: $locale !== '');

        return new DefaultLocale(locale: $locale);
    }
}
