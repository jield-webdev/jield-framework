<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\View\Helper;
use Laminas\Translator\TranslatorInterface;
use Laminas\View\HelperPluginManager;

use function count;
use function md5;

/**
 * Class FormCheckbox
 * @package Jield\Bootstrap5\Form\View\Helper
 */
final class FormCheckbox extends Helper\FormCheckbox
{
    private Helper\FormLabel $formLabel;

    public function __construct(HelperPluginManager $viewHelperManager, TranslatorInterface $translator)
    {
        $this->formLabel = $viewHelperManager->get(id: 'formlabel');

        $this->translator = $translator;
    }

    public function render(ElementInterface $element): string
    {
        $element->setAttribute(key: 'class', value: 'form-check-input ' . $element->getAttribute(key: 'class'));

        if (count(value: $element->getMessages()) > 0) {
            $element->setAttribute(key: 'class', value: 'form-check-input is-invalid');
        }

        $id = md5(string: $element->getName());
        $element->setAttribute(key: 'id', value: $id);

        $renderedElement = parent::render(element: $element);

        $template = '<div class="form-check form-switch">%s<label class="form-check-label" for="%s">%s</label></div>';

        return sprintf($template, $renderedElement, $id, $this->findLabel(element: $element));
    }

    private function findLabel(ElementInterface $element): ?string
    {
        $label = $element->getAttribute(key: 'label') ?? $element->getLabel();

        if (null !== ($translator = $this->formLabel->getTranslator())) {
            $label = $translator->translate($label);
        }

        return $label;
    }
}
