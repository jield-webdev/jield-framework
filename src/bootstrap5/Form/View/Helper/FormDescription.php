<?php

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
    private string $blockWrapper  = '<small class="form-text">%s</small>';

    public function __invoke(
        ?ElementInterface $element = null,
        ?string           $blockWrapper = null,
        ?string           $inlineWrapper = null
    ): string|FormDescription
    {
        if ($element) {
            return $this->render(element: $element, blockWrapper: $blockWrapper, inlineWrapper: $inlineWrapper);
        }

        return $this;
    }

    public function render(ElementInterface $element, ?string $blockWrapper = null, ?string $inlineWrapper = null): string
    {
        $blockWrapper  = $blockWrapper ?: $this->blockWrapper;
        $inlineWrapper = $inlineWrapper ?: $this->inlineWrapper;

        $html = '';
        if ($inline = $element->getOption(option: 'help-inline')) {
            if (null !== ($translator = $this->getTranslator())) {
                $inline = $translator->translate(
                    $inline,
                    $this->getTranslatorTextDomain()
                );
            }

            $html .= sprintf($inlineWrapper, $inline);
        }

        if ($block = $element->getOption(option: 'help-block')) {
            if (null !== ($translator = $this->getTranslator())) {
                $block = $translator->translate(
                    $block,
                    $this->getTranslatorTextDomain()
                );
            }

            $html .= sprintf($blockWrapper, $block);
        }

        return $html;
    }
}
