<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Loader\Factory;

use Jield\I18n\Exception\RuntimeException;
use Jield\I18n\Translator\Loader\IniFileReader;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function is_array;
use function is_bool;
use function is_iterable;
use function is_string;
use function iterator_to_array;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class IniFileReaderFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): IniFileReader {
        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        /** @psalm-var mixed $i18n */
        $i18n = $config['Jield-i18n'] ?? [];
        $i18n = is_array(value: $i18n) ? $i18n : [];

        /** @psalm-var mixed $configuredOptions */
        $configuredOptions = $i18n['ini-format-options'] ?? [];
        $configuredOptions = is_array(value: $configuredOptions) ? $configuredOptions : [];

        $processSections = $configuredOptions['process-sections'] ?? true;
        $typed           = $configuredOptions['typed'] ?? false;
        $nestSeparator   = $configuredOptions['nest-separator'] ?? '.';
        if (! is_bool(value: $processSections) || ! is_bool(value: $typed) || ! is_string(value: $nestSeparator) || $nestSeparator === '') {
            throw new RuntimeException(message: 'Invalid option configuration for ini file reader');
        }

        return new IniFileReader(nestSeparator: $nestSeparator, processSections: $processSections, typed: $typed);
    }
}
