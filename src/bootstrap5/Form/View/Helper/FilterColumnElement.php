<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\Element\MultiCheckbox;
use Jield\Form\ElementInterface;
use Jield\Form\Fieldset;
use Jield\Form\FieldsetInterface;
use Jield\Form\Form;
use Jield\Search\ValueObject\FacetField;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

use function implode;
use function sprintf;

/**
 * Class FilterColumnElement
 * @package Jield\Bootstrap5\Form\View\Helper
 */
class FilterColumnElement extends FormElement
{
    protected const string HEADSCRIPT = 'HeadScript';
    protected const string HEADLINK = 'HeadLink';

    protected bool $rendered = false;

    public function __invoke(
        ?ElementInterface $element = null,
        $type = self::TYPE_HORIZONTAL,
        bool $formElementOnly = false,
    ): string|static {
        $this->appendScript();

        if ($element instanceof Form) {
            return $this->renderFacets(form: $element);
        }

        return $this;
    }

    protected function getContainer(string $containerName)
    {
        return $this->getView()->plugin($containerName);
    }

    private function appendScript(): void
    {
        Value::call($this->getContainer(containerName: self::HEADSCRIPT), 'appendFile', [
            'assets/js/jield-bootstrap5/filter-column.js',
            ['type' => 'text/javascript'] + [],
        ]);
        Value::call(
            $this->getContainer(containerName: self::HEADLINK),
            'appendStylesheet',
            ['assets/css/jield-bootstrap5/filter-column.css'],
        );
    }

    private function renderFacets(Form $form): string
    {
        if (!$form->has(elementOrFieldset: 'facet')) {
            return 'no facets found';
        }

        $facets = [];
        /** @var Fieldset $facet */
        foreach (Assert::isInstanceOf(
            $form->get(elementOrFieldset: 'facet'),
            FieldsetInterface::class,
        )->getFieldsets() as $facet) {
            $facets[] = '<div class="simple-load-more">';
            $facets[] = sprintf('<strong>%s</strong>', $facet->get(elementOrFieldset: 'values')->getLabel());

            if ($facet->has(elementOrFieldset: 'yesNo')) {
                $facet->get(elementOrFieldset: 'yesNo')->setAttribute(
                    key: 'class',
                    value: 'form-check-search form-check-yes-no',
                );
                $facet->get(elementOrFieldset: 'yesNo')->setLabel(label: 'Include');
                if ($facet->get(elementOrFieldset: 'yesNo')->getValue() === 'no') {
                    $facet->get(elementOrFieldset: 'yesNo')->setLabel(label: 'Exclude');
                }
                $facets[] = $this->renderRaw(element: $facet->get(elementOrFieldset: 'yesNo'));
            }

            if ($facet->has(elementOrFieldset: 'andOr')) {
                $facet->get(elementOrFieldset: 'andOr')->setAttribute(
                    key: 'class',
                    value: 'form-check-search form-check-and-or',
                );
                $facet->get(elementOrFieldset: 'andOr')->setLabel(label: 'Or');
                if ($facet->get(elementOrFieldset: 'andOr')->getValue() === 'and') {
                    $facet->get(elementOrFieldset: 'andOr')->setLabel(label: 'And');
                }
                $facets[] = $this->renderRaw(element: $facet->get(elementOrFieldset: 'andOr'));
            }

            $facets[] = $this->renderRaw(element: $facet->get(elementOrFieldset: 'values'));
            $facets[] = '</div>';
        }

        return implode(separator: PHP_EOL, array: $facets);
    }

    private function renderRaw(ElementInterface $element): ?string
    {
        $type = $element->getAttribute(key: 'type');

        switch ($type) {
            case 'multi_checkbox':
                //Based om the type we can choose to render a multi-checkbox as a slider
                if ($element->getOption(option: 'type') === FacetField::TYPE_SLIDER) {
                    //Get the helper
                    /** @var FormMultiCheckbox $formMultiCheckbox */
                    $formMultiCheckbox = $this->getView()->plugin('lbs5formmultislider');

                    return $formMultiCheckbox->render(element: $element);
                }

                //Get the helper
                /** @var FormMultiCheckbox $formMultiCheckbox */
                $formMultiCheckbox = $this->getView()->plugin('lbs5formmulticheckbox');
                $formMultiCheckbox->setTemplate(
                    template: '<div class="form-check form-check-search" data-other="%s">%s%s%s%s</div>',
                );

                //Reset the options so the checked one is always on top
                $sortedValueOptions = [];
                $currentValueOptions = Assert::isInstanceOf($element, MultiCheckbox::class)->getValueOptions();

                foreach (Value::toArray($element->getValue()) as $value) {
                    //We can only unset the value if it is in the value options
                    if (!array_key_exists(key: $value, array: $currentValueOptions)) {
                        continue;
                    }

                    $sortedValueOptions[$value] = $currentValueOptions[$value];
                    unset($currentValueOptions[$value]);
                }

                Assert::isInstanceOf($element, MultiCheckbox::class)->setValueOptions(options: $sortedValueOptions
                + $currentValueOptions);

                return $formMultiCheckbox->render(element: $element);
            case 'checkbox':
                //Get the helper
                /** @var FormCheckbox $formMultiCheckbox */
                $formMultiCheckbox = $this->getView()->plugin('lbs5formcheckbox');

                return $formMultiCheckbox->render(element: $element);
            case 'radio':
                //Get the helper
                /** @var FormMultiCheckbox $formMultiCheckbox */
                $formMultiCheckbox = $this->getView()->plugin('lbs5formradio');
                $formMultiCheckbox->setTemplate(
                    template: '<div class="form-check form-check-search %s">%s%s%s%s</div>',
                );

                return $formMultiCheckbox->render(element: $element);
            default:
                return $this->renderHelper(name: Assert::string($type), element: $element);
        }
    }
}
