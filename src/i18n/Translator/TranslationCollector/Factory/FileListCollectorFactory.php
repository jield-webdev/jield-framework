<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\TranslationCollector\Factory;

use Jield\I18n\I18nDefaults;
use Jield\I18n\Translator\MessageLoaderPluginManagerInterface;
use Jield\I18n\Translator\TranslationCollector\FileListCollector;
use Jield\I18n\Translator\Value\TranslationFile;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;
use function is_iterable;
use function iterator_to_array;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class FileListCollectorFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): FileListCollector {
        $defaults = $container->get(I18nDefaults::class);
        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        $i18n = $config['Jield-i18n'] ?? [];
        assert(assertion: is_array(value: $i18n));

        $translator = $i18n['translator'] ?? [];
        assert(assertion: is_array(value: $translator));

        $files = $translator['translation_files'] ?? [];
        assert(assertion: is_array(value: $files));

        $fileList = [];

        /** @psalm-var mixed $spec */
        foreach ($files as $spec) {
            if (! is_array(value: $spec)) {
                continue;
            }

            $file = TranslationFile::fromArray(
                spec: $spec,
                defaultLocale: $defaults->defaultLocale,
                defaultTextDomain: $defaults->defaultTextDomain,
            );

            $fileList[$file->textDomain]                ??= [];
            $fileList[$file->textDomain][$file->locale] ??= [];
            $fileList[$file->textDomain][$file->locale][] = $file;
        }

        return new FileListCollector(files: $fileList, loader: $container->get(MessageLoaderPluginManagerInterface::class));
    }
}
