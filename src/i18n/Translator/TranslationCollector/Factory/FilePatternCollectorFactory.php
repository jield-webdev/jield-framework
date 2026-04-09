<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\TranslationCollector\Factory;

use Jield\I18n\I18nDefaults;
use Jield\I18n\Translator\MessageLoaderPluginManagerInterface;
use Jield\I18n\Translator\TranslationCollector\FilePatternCollector;
use Jield\I18n\Translator\Value\TranslatorFilePattern;
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
final readonly class FilePatternCollectorFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): FilePatternCollector {
        $defaults = $container->get(I18nDefaults::class);
        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        $i18n = $config['Jield-i18n'] ?? [];
        assert(assertion: is_array(value: $i18n));

        $translator = $i18n['translator'] ?? [];
        assert(assertion: is_array(value: $translator));

        $patterns = $translator['translation_file_patterns'] ?? [];
        assert(assertion: is_array(value: $patterns));

        $patternList = [];

        foreach ($patterns as $spec) {
            if (! is_array(value: $spec)) {
                continue;
            }

            $pattern = TranslatorFilePattern::fromArray(
                spec: $spec,
                defaultTextDomain: $defaults->defaultTextDomain,
            );

            $patternList[$pattern->textDomain][] = $pattern;
        }

        return new FilePatternCollector(patterns: $patternList, loader: $container->get(MessageLoaderPluginManagerInterface::class));
    }
}
