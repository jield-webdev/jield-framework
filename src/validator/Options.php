<?php

declare(strict_types=1);

namespace Jield\Validator;

use Jield\Stdlib\Value;
use Laminas\Translator\TranslatorInterface;
use Laminas\Validator\AbstractValidator;

/** @psalm-import-type AbstractOptions from AbstractValidator */
final class Options
{
    /** @return AbstractOptions */
    public static function normalize(array $options): array
    {
        $options = Value::stringKeyedArray($options);
        if (array_key_exists('messages', $options)) {
            $options['messages'] = Value::stringMap($options['messages']);
        }
        if (array_key_exists('translator', $options)) {
            $options['translator'] = Value::nullableInstance($options['translator'], TranslatorInterface::class);
        }
        if (array_key_exists('translatorTextDomain', $options)) {
            $options['translatorTextDomain'] = Value::nullableString($options['translatorTextDomain']);
        }
        foreach (['translatorEnabled', 'valueObscured'] as $key) {
            if (array_key_exists($key, $options)) {
                $options[$key] = Value::bool($options[$key]);
            }
        }

        /** @var AbstractOptions $options All standard options have been checked; custom options remain intact. */
        return $options;
    }
}
