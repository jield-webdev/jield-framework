<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\TranslationCollector\Factory;

use Jield\I18n\Exception\InvalidArgumentException;
use Jield\I18n\I18nDefaults;
use Jield\I18n\Translator\MessageLoaderPluginManagerInterface;
use Jield\I18n\Translator\TranslationCollector\RemoteListCollector;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;
use function is_iterable;
use function is_string;
use function iterator_to_array;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class RemoteListCollectorFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): RemoteListCollector {
        $defaults = $container->get(I18nDefaults::class);

        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        $i18n = $config['Jield-i18n'] ?? [];
        assert(assertion: is_array(value: $i18n));

        $translator = $i18n['translator'] ?? [];
        assert(assertion: is_array(value: $translator));

        $remotes = $translator['remote_translation'] ?? [];
        assert(assertion: is_array(value: $remotes));

        $remoteList = [];

        foreach ($remotes as $remote) {
            if (! is_array(value: $remote)) {
                continue;
            }

            /** @psalm-var mixed $type */
            $type = $remote['type'] ?? null;
            /** @psalm-var mixed $textDomain */
            $textDomain = $remote['text_domain'] ?? $defaults->defaultTextDomain;
            if (! is_string(value: $type) || $type === '') {
                throw new InvalidArgumentException(message: 'The `type` key for each remote loader must be a string');
            }
            if (! is_string(value: $textDomain) || $textDomain === '') {
                throw new InvalidArgumentException(message: 'The `text_domain` key must resolve to a non-empty-string');
            }

            $remoteList[$textDomain] ??= [];
            $remoteList[$textDomain][] = $type;
        }

        return new RemoteListCollector(remotes: $remoteList, loader: $container->get(MessageLoaderPluginManagerInterface::class));
    }
}
