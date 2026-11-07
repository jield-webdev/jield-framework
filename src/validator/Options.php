<?php

declare(strict_types=1);

namespace Jield\Validator;

use Laminas\Translator\TranslatorInterface;
use Laminas\Validator\AbstractValidator;
use Webmozart\Assert\Assert;

/** @psalm-import-type AbstractOptions from AbstractValidator */
final class Options
{
    /** @return AbstractOptions */
    public static function normalize(array $options): array
    {
        if (array_key_exists('messages', $options)) {
            Assert::isArray($options['messages']);
            Assert::allString(array_keys($options['messages']));
            Assert::allString($options['messages']);
        }
        if (array_key_exists('translator', $options)) {
            $options['translator'] = Assert::nullOrIsInstanceOf($options['translator'], TranslatorInterface::class);
        }
        if (array_key_exists('translatorTextDomain', $options)) {
            $options['translatorTextDomain'] = Assert::nullOrString($options['translatorTextDomain']);
        }
        foreach (['translatorEnabled', 'valueObscured'] as $key) {
            if (!array_key_exists($key, $options)) {
                continue;
            }

            $options[$key] = Assert::boolean($options[$key]);
        }

        /** @var AbstractOptions $options All standard options have been checked; custom options remain intact. */
        return $options;
    }
}
