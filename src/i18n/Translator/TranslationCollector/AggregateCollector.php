<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\TranslationCollector;

use Jield\I18n\Translator\TextDomain;

final readonly class AggregateCollector implements TranslationCollectorInterface
{
    /** @param list<TranslationCollectorInterface> $collectors */
    public function __construct(private array $collectors)
    {
    }

    public function collect(string $textDomain, string $locale): TextDomain
    {
        $result = new TextDomain();
        foreach ($this->collectors as $collector) {
            $result->merge(textDomain: $collector->collect(textDomain: $textDomain, locale: $locale));
        }

        return $result;
    }
}
