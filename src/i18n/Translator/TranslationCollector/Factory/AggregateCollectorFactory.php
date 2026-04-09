<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\TranslationCollector\Factory;

use Jield\I18n\Exception\InvalidArgumentException;
use Jield\I18n\Translator\TranslationCollector\AggregateCollector;
use Jield\I18n\Translator\TranslationCollector\FileListCollector;
use Jield\I18n\Translator\TranslationCollector\FilePatternCollector;
use Jield\I18n\Translator\TranslationCollector\RemoteListCollector;
use Jield\I18n\Translator\TranslationCollector\TranslationCollectorInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;
use function is_iterable;
use function is_string;
use function iterator_to_array;

final readonly class AggregateCollectorFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): AggregateCollector {
        /**
         * Defines the default collectors (And the order of them) that will be instantiated
         * when no configuration is present.
         */
        $defaultCollectors = [
            RemoteListCollector::class,
            FilePatternCollector::class,
            FileListCollector::class,
        ];

        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        $i18n = $config['Jield-i18n'] ?? [];
        assert(assertion: is_array(value: $i18n));

        $translator = $i18n['translator'] ?? [];
        assert(assertion: is_array(value: $translator));

        $collectorList = $translator['aggregate_collector'] ?? $defaultCollectors;
        assert(assertion: is_array(value: $collectorList));

        $collectors = [];

        foreach ($collectorList as $id) {
            if (! is_string(value: $id)) {
                throw new InvalidArgumentException(message: 'Each item in the `aggregate_collector` list must be a string');
            }

            $collector = $container->get($id);
            assert(assertion: $collector instanceof TranslationCollectorInterface);

            $collectors[] = $collector;
        }

        return new AggregateCollector(collectors: $collectors);
    }
}
