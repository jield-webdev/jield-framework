<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\TranslationCollector;

use Jield\I18n\Exception\RuntimeException;
use Jield\I18n\Translator\Loader\RemoteLoaderInterface;
use Jield\I18n\Translator\MessageLoaderPluginManagerInterface;
use Jield\I18n\Translator\TextDomain;

use function sprintf;

/**
 * Defines a list of remote loaders per text domain
 *
 * @psalm-type TextDomainKey = non-empty-string
 * @psalm-type LoaderTypeValue = non-empty-string|class-string<RemoteLoaderInterface>
 * @psalm-type RemoteList = array<TextDomainKey, list<LoaderTypeValue>>
 */
final readonly class RemoteListCollector implements TranslationCollectorInterface
{
    /** @param RemoteList $remotes */
    public function __construct(
        private array $remotes,
        private MessageLoaderPluginManagerInterface $loader,
    ) {
    }

    public function collect(string $textDomain, string $locale): TextDomain
    {
        $result  = new TextDomain();
        $loaders = $this->remotes[$textDomain] ?? [];
        foreach ($loaders as $loaderType) {
            $loader = $this->loader->has($loaderType) ? $this->loader->get($loaderType) : null;

            if (! $loader instanceof RemoteLoaderInterface) {
                throw new RuntimeException(message: sprintf(
                    'The specified loader "%s" is not a remote loader',
                    $loaderType,
                ));
            }

            $messages = $loader->load(locale: $locale, textDomain: $textDomain);
            if ($messages === null) {
                continue;
            }

            $result->merge(textDomain: $messages);
        }

        return $result;
    }
}
