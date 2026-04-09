<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Loader\Factory;

use Jield\I18n\Translator\Loader\Ini;
use Jield\I18n\Translator\Loader\IniFileReader;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class IniFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Ini {
        return new Ini(fileReader: $container->get(IniFileReader::class));
    }
}
