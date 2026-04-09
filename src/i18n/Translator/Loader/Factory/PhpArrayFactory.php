<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Loader\Factory;

use Jield\I18n\Exception\RuntimeException;
use Jield\I18n\Translator\Loader\PhpArray;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function is_array;
use function is_bool;
use function is_iterable;
use function iterator_to_array;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class PhpArrayFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): PhpArray {
        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        /** @psalm-var mixed $i18n */
        $i18n = $config['Jield-i18n'] ?? [];
        $i18n = is_array(value: $i18n) ? $i18n : [];

        /** @psalm-var mixed $configuredOptions */
        $configuredOptions = $i18n['php-loader-options'] ?? [];
        $configuredOptions = is_array(value: $configuredOptions) ? $configuredOptions : [];

        $useIncludePath = $configuredOptions['use-include-path'] ?? false;
        if (! is_bool(value: $useIncludePath)) {
            throw new RuntimeException(message: 'The PHP array loader include path option must be boolean');
        }

        return new PhpArray(useIncludePath: $useIncludePath);
    }
}
