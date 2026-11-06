<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Helper;

use Laminas\I18n\Exception;

/**
 * View helper for translating messages.
 *
 * @final
 */
class Translate extends AbstractTranslatorHelper
{
    /**
     * Translate a message
     *
     * @param  string      $message
     * @param  string|null $textDomain
     * @param  string|null $locale
     * @throws Exception\RuntimeException
     * @return string
     */
    public function __invoke($message, $textDomain = null, $locale = null)
    {
        $translator = $this->getTranslator();
        if (null === $translator) {
            throw new Exception\RuntimeException('Translator has not been set');
        }
        if (null === $textDomain) {
            $textDomain = $this->getTranslatorTextDomain();
        }

        return $translator->translate($message, $textDomain, $locale);
    }
}
