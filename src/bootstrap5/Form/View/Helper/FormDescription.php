<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\View\Helper\AbstractHelper;

use function sprintf;

/**
 * Class FormDescription
 * @package Jield\Bootstrap5\Form\View\Helper
 */
final class FormDescription extends AbstractHelper
{
    private string $inlineWrapper = '<small class="form-text">%s</small>';
    private string $blockWrapper = '<small class="form-text">%s</small>';

    /** @return ($element is null ? self : string) */
    public function __invoke(
        ?ElementInterface $element = null,
        ?string $blockWrapper = null,
        ?string $inlineWrapper = null,
    ): string|FormDescription {
        if ($element) {
            return $this->render(element: $element, blockWrapper: $blockWrapper, inlineWrapper: $inlineWrapper);
        }

        return $this;
    }

    public function render(
        ElementInterface $element,
        ?string $blockWrapper = null,
        ?string $inlineWrapper = null,
    ): string {
        $blockWrapper = $blockWrapper ?: $this->blockWrapper;
        $inlineWrapper = $inlineWrapper ?: $this->inlineWrapper;

        $html = '';
        /** @var mixed $inline */
        if ($inline = $element->getOption(option: 'help-inline')) {
            if (null !== ($translator = $this->getTranslator())) {
                $inline = $translator->translate(\Jield\Stdlib\Value::string($inline), $this->getTranslatorTextDomain());
            }

            $html .= sprintf($inlineWrapper, \Jield\Stdlib\Value::scalar($inline));
        }

        /** @var mixed $block */
        if ($block = $element->getOption(option: 'help-block')) {
            if (null !== ($translator = $this->getTranslator())) {
                $block = $translator->translate(\Jield\Stdlib\Value::string($block), $this->getTranslatorTextDomain());
            }

            $html .= sprintf($blockWrapper, \Jield\Stdlib\Value::scalar($block));
        }

        return $html;
    }
}
