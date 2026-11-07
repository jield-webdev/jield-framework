<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\Fieldset;
use Jield\Form\FieldsetInterface;
use Jield\Form\Form;
use Webmozart\Assert\Assert;

use function implode;
use function sprintf;

class FilterBarElement extends FormElement
{
    public function __invoke(
        ?ElementInterface $element = null,
        $type = false,
        bool $formElementOnly = false,
    ): string|static {
        if ($element instanceof Form) {
            return $this->renderFilterBar(element: $element);
        }

        return $this;
    }

    private function renderFilterBar(Form $element): string
    {
        $wrapper = '
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#filterBar"
                        aria-controls="filterBar" aria-expanded="false" aria-label="Toggle Filter">
                    <span class="navbar-toggler-icon"></span>
                </button>
        
                <div class="collapse navbar-collapse" id="filterBar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                         %s                   
                    </ul>
                    <div class="d-flex">
                    <div class="input-group">
                            %s
                            %s
                            %s
                            </div>
                    </div>
                </div>
            </div>
        </nav>
        
        <script type="text/javascript">
            $(\'.dropdown-menu-filter-bar\').on(\'click\', function(e) {
                e.stopPropagation();
            });
        
            $(function () {
                $(\'#searchButton\').on(\'click\', function () {
                    $(\'#search\').submit();
                });
                $(\'#resetButton\').on(\'click\', function () {
                    $(\'input[type="checkbox"]\').each(function () {
                        this.removeAttribute(\'checked\');
                    });
                    $(\'input[name="query"]\').val(\'\');
                    $(\'#search\').submit();
                });
            });
        </script>
        

    ';

        return sprintf(
            $wrapper,
            $this->renderFacets(form: $element),
            $this->renderRaw(element: $element->get(elementOrFieldset: 'query')),
            $this->renderRaw(element: $element->get(elementOrFieldset: 'search')),
            $this->renderRaw(element: $element->get(elementOrFieldset: 'reset')),
        );
    }

    private function renderFacets(Form $form): string
    {
        $facets = [];

        $facetWrapper = '<li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="searchDropdown-%d" role="button"
                           data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            %s
                        </a>                        
                        <div class="dropdown-menu inactive dropdown-menu-filter-bar" aria-labelledby="searchDropdown-%d">
                            %s
                            %s
                            %s
                                                                        
                    </li>';

        /** @var Fieldset $facet */
        foreach (Assert::isInstanceOf(
            $form->get(elementOrFieldset: 'facet'),
            FieldsetInterface::class,
        )->getFieldsets() as $counter => $facet) {
            $yesNo = '';
            if ($facet->has(elementOrFieldset: 'yesNo')) {
                $facet->get(elementOrFieldset: 'yesNo')->setAttribute(
                    key: 'class',
                    value: 'form-check-search form-check-yes-no',
                );
                $facet->get(elementOrFieldset: 'yesNo')->setLabel(label: 'Include');
                if ($facet->get(elementOrFieldset: 'yesNo')->getValue() === 'no') {
                    $facet->get(elementOrFieldset: 'yesNo')->setLabel(label: 'Exclude');
                }
                $yesNo =
                    '<div class="dropdown-item"><div class="form-check">'
                    . (string) $this->renderRaw(element: $facet->get(elementOrFieldset: 'yesNo'))
                    . '</div></div>';
            }

            $andOr = '';
            if ($facet->has(elementOrFieldset: 'andOr')) {
                $facet->get(elementOrFieldset: 'andOr')->setAttribute(
                    key: 'class',
                    value: 'form-check-search form-check-and-or',
                );
                $facet->get(elementOrFieldset: 'andOr')->setLabel(label: 'Or');
                if ($facet->get(elementOrFieldset: 'andOr')->getValue() === 'and') {
                    $facet->get(elementOrFieldset: 'andOr')->setLabel(label: 'And');
                }
                $andOr =
                    '<div class="dropdown-item"><div class="form-check">'
                    . (string) $this->renderRaw(element: $facet->get(elementOrFieldset: 'andOr'))
                    . '</div></div>';
            }

            $facets[] = sprintf(
                $facetWrapper,
                $counter,
                $facet->get(elementOrFieldset: 'values')->getLabel(),
                $counter,
                $yesNo,
                $andOr,
                $this->renderRaw(element: $facet->get(elementOrFieldset: 'values')),
            );
        }

        return implode(separator: PHP_EOL, array: $facets);
    }

    private function renderRaw(ElementInterface $element): string
    {
        $type = $element->getAttribute(key: 'type');

        switch ($type) {
            case 'multi_checkbox':
                //Get the helper
                /** @var FormMultiCheckbox $formMultiCheckbox */
                $formMultiCheckbox = $this->getView()->plugin('lbs5formmulticheckbox');
                $formMultiCheckbox->setTemplate(
                    template: '<div class="dropdown-item"><div class="form-check %s">%s%s%s%s</div></div>',
                );

                return $formMultiCheckbox->render(element: $element);
            case 'radio':
                //Get the helper
                /** @var FormMultiCheckbox $formMultiCheckbox */
                $formMultiCheckbox = $this->getView()->plugin('lbs5formradio');
                $formMultiCheckbox->setTemplate(
                    template: '<div class="dropdown-item"><div class="form-check %s">%s%s%s%s</div></div>',
                );

                return $formMultiCheckbox->render(element: $element);
            case 'select':
                //Get the helper
                /** @var FormMultiCheckbox $formMultiCheckbox */
                $formMultiCheckbox = $this->getView()->plugin('lbs5formselect');

                return $formMultiCheckbox->render(element: $element);
            case 'checkbox':
                //Get the helper
                /** @var FormCheckbox $formMultiCheckbox */
                $formMultiCheckbox = $this->getView()->plugin('lbs5formcheckbox');

                return $formMultiCheckbox->render(element: $element);
            case 'text':
            case 'search':
                $element->setAttribute(
                    key: 'class',
                    value: 'form-control ' . (string) $element->getAttribute(key: 'class'),
                );
                return $this->renderHelper(name: 'lbs5forminput', element: $element);
            case 'button':
                $element->setAttribute(key: 'id', value: 'searchButton');
                if ($element->getName() === 'reset') {
                    $element->setAttribute(key: 'id', value: 'resetButton');
                }
                return $this->renderHelper(name: 'formbutton', element: $element);
            case 'submit':
                return $this->renderHelper(name: 'formsubmit', element: $element);
            default:
                return $this->renderHelper(name: Assert::string($type), element: $element);
        }
    }
}
