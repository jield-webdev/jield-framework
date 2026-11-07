<?php

declare(strict_types=1);

namespace Jield\Search\Form;

use Jield\Form\Element\Checkbox;
use Jield\Form\Element\MultiCheckbox;
use Jield\Form\Element\Text;
use Jield\Form\Fieldset;
use Jield\Search\Enum\FacetFieldVisibilityEnum;
use Jield\Search\Service\AbstractSearchService;
use Jield\Search\ValueObject\FacetField;
use Jield\Stdlib\Value;
use Laminas\InputFilter\InputFilterProviderInterface;
use RuntimeException;
use Solarium\Component\Result\Facet\Field;
use Webmozart\Assert\Assert;

use function array_reverse;
use function count;
use function http_build_query;
use function sprintf;

class SolrSearchFilter extends SearchFilter implements InputFilterProviderInterface
{
    public function __construct(
        private readonly AbstractSearchService $searchService,
        array $fields = [],
        string $method = 'get',
        bool $hasDateInterval = false,
        FacetFieldVisibilityEnum $facetFieldVisibilityEnum = FacetFieldVisibilityEnum::FILTER_COLUMN,
    ) {
        parent::__construct();

        $this->setAttribute(key: 'method', value: $method);
        $this->setAttribute(key: 'action', value: '');

        if ($hasDateInterval) {
            $this->add(elementOrFieldset: [
                'type' => Text::class,
                'name' => 'dateInterval',
                'options' => [
                    'is-date-range' => true,
                    'label' => 'Date interval',
                    'visibility' => $facetFieldVisibilityEnum,
                ],
            ]);
        }

        $filter = new Fieldset(name: 'filter');

        // Add the field selection
        if (!empty($fields)) {
            /** @mago-var mixed $values */
            foreach ($fields as $key => $values) {
                $filter->add(elementOrFieldset: [
                    'type' => MultiCheckbox::class,
                    'name' => $key,
                    'options' => [
                        'label' => $key,
                        'value_options' => $values,
                        'visibility' => $facetFieldVisibilityEnum,
                    ],
                ]);
            }
        }

        $this->add(elementOrFieldset: $filter);
    }

    public function createFacetFormElements(): void
    {
        $facetFieldset = new Fieldset(name: 'facet');

        foreach ($this->searchService->getFacets() as $facetField) {
            $facetElementFieldset = new Fieldset(name: $facetField->getField());
            $facetElementFieldset->setOption(key: 'visibility', value: $facetField->getvisibility());

            /** @mago-var mixed $field */
            $field = $this->getFacetByFacetField(fieldName: $facetField->getField());

            if ($facetField->getHasYesNo()) {
                $facetElementFieldset->add(elementOrFieldset: $this->createYesNoFormElement());
            }

            $facetElementFieldset->add(elementOrFieldset: $this->createFacetFieldFormElement(
                field: Assert::isInstanceOf($field, \Solarium\Component\Result\Facet\Field::class),
                facetField: $facetField,
            ));

            if ($facetField->getHasAndOr()) {
                $facetElementFieldset->add(elementOrFieldset: $this->createAndOrFormElement());
            }

            if ((is_countable(value: $field) ? count(value: $field) : 0) > 0) {
                $facetFieldset->add(elementOrFieldset: $facetElementFieldset);
            }
        }

        $this->add(elementOrFieldset: $facetFieldset);
    }

    private function getFacetByFacetField(string $fieldName)
    {
        $facetSet = $this->searchService->getResultSet()->getFacetSet();

        if (null === $facetSet) {
            throw new RuntimeException(message: 'This search has no facets');
        }

        $facetField = $this->searchService->getFacet(fieldName: $fieldName);

        return Value::call($facetSet, 'getFacet', [$facetField->getField()]);
    }

    private function createYesNoFormElement(): Checkbox
    {
        $yesNoElement = new Checkbox();
        $yesNoElement->setName(name: 'yesNo');
        $yesNoElement->setLabel(label: 'txt-yes-no');
        $yesNoElement->setCheckedValue(checkedValue: 'no'); //niet
        $yesNoElement->setUseHiddenElement(useHiddenElement: false); //en

        return $yesNoElement;
    }

    private function createFacetFieldFormElement(Field $field, FacetField $facetField): MultiCheckbox
    {
        $multiOptions = [];
        /** @mago-var mixed $value */
        foreach ($field as $key => $value) {
            $multiOptions[$key] = sprintf('%s <small class="text-muted">(%s)</small>', $key, Value::scalar($value));
        }

        if ($facetField->getReverse()) {
            $multiOptions = array_reverse(array: $multiOptions, preserve_keys: true);
        }

        $facetElement = new MultiCheckbox();
        $facetElement->setName(name: 'values');
        $facetElement->setValue(value: $facetField->getDefaultValue());
        $facetElement->setLabel(label: $facetField->getName());
        $facetElement->setValueOptions(options: $multiOptions);
        $facetElement->setLabelOption(key: 'escape', value: false);
        $facetElement->setLabelOption(key: 'disable_html_escape', value: true);
        $facetElement->setOption(key: 'inline', value: true);
        $facetElement->setOption(key: 'type', value: $facetField->getType());
        $facetElement->setDisableInArrayValidator(disableOption: true);

        return $facetElement;
    }

    private function createAndOrFormElement(): Checkbox
    {
        $andOrElement = new Checkbox();
        $andOrElement->setName(name: 'andOr');
        $andOrElement->setLabel(label: 'txt-and-or');
        $andOrElement->setCheckedValue(checkedValue: 'and'); //en
        $andOrElement->setUseHiddenElement(useHiddenElement: false); //en

        return $andOrElement;
    }

    public function hasBadges(): bool
    {
        return count(value: $this->getBadges()) > 0;
    }

    public function getBadges(): array
    {
        $submittedData = $this->data ?? [];
        $badges = [];

        if (!empty($submittedData['query'])) {
            $data = [
                'facet' => $submittedData['facet'],
                'filter' => $submittedData['filter'],
                'dateInterval' => $submittedData['dateInterval'] ?? null,
                'query' => '',
            ];

            $badges[] = [
                'type' => 'search',
                'query' => $submittedData['query'],
                'facetArguments' => http_build_query(data: $data),
                'resetEncodedFilter' => base64_encode(string: Assert::string(json_encode(value: $data))),
            ];
        }

        if (!empty($submittedData['dateInterval'])) {
            $data = [
                'facet' => $submittedData['facet'],
                'filter' => $submittedData['filter'],
                'query' => '',
            ];

            $badges[] = [
                'type' => 'dateInterval',
                'value' => $submittedData['dateInterval'],
                'facetArguments' => http_build_query(data: $data),
                'resetEncodedFilter' => base64_encode(string: Assert::string(json_encode(value: $data))),
            ];
        }

        if (!empty($submittedData['filter']['general'])) {
            /** @mago-var mixed $selectedValues */
            $selectedValues = $submittedData['filter']['general'];
            /** @mago-var mixed $value */
            foreach (Assert::isIterable($selectedValues) as $value) {
                $remainingValues = array_diff(Value::scalars($selectedValues), Value::scalars([$value]));

                $data = [
                    'facet' => $submittedData['facet'],
                    'filter' => [
                        'general' => $remainingValues,
                    ],
                    'query' => $submittedData['query'],
                ];

                if (!empty($submittedData['dateInterval'])) {
                    $data['dateInterval'] = $submittedData['dateInterval'];
                }

                $badges[] = [
                    'type' => 'general',
                    'value' => $value,
                    'facetArguments' => http_build_query(data: $data),
                    'resetEncodedFilter' => base64_encode(string: Assert::string(json_encode(value: $data))),
                ];
            }
        }

        /**
         * @mago-var mixed $facetName
         * @mago-var mixed $facetData
         */
        foreach (Assert::isIterable($submittedData['facet'] ?? []) as $facetName => $facetData) {
            //We need to check if the facet is actually a facet field
            if (!$this->searchService->hasFacet(fieldName: Assert::string($facetName))) {
                continue;
            }

            $facetField = $this->searchService->getFacet(fieldName: Assert::string($facetName));

            /** @mago-var mixed $values */
            $values = $facetData['values'] ?? [];

            if (is_string(value: $values) && $facetField->isSlider()) {
                $values = array_map(callback: intval(...), array: explode(separator: ',', string: $values));
                $valueText = sprintf('BETWEEN %s and %s', $values[0], $values[1] ?? '');
            } elseif ($facetField->isCheckboxMin()) {
                $valueText = sprintf('AT LEAST %d', Value::scalar($values[0] ?? ''));
            } else {
                $valueText = implode(separator: $facetData['andOr'] ?? false ? ' and ' : ' or ', array: Value::scalars($values));
            }

            //Remaining facets are all facets where the current facet value is filtered out
            $remainingFacets = Assert::isArray($submittedData['facet']);

            unset($remainingFacets[$facetName]);

            $data = [
                'query' => $submittedData['query'],
                'facet' => $remainingFacets,
                'filter' => $submittedData['filter'],
            ];

            if (!empty($submittedData['dateInterval'])) {
                $data['dateInterval'] = $submittedData['dateInterval'];
            }

            $badges[] = [
                'type' => 'facet',
                'facetField' => $facetField,
                'name' => $facetField->getName(),
                'values' => $valueText,
                'hasValues' => (is_countable(value: $values) ? count(value: $values) : 0) > 0,
                'not' => !(isset($facetData['yesNo']) && $facetData['yesNo'] === 'no'),
                'facetArguments' => http_build_query(data: $data),
                'resetEncodedFilter' => base64_encode(string: json_encode(value: $data, flags: JSON_THROW_ON_ERROR)),
            ];
        }

        return $badges;
    }

    public function getInputFilterSpecification(): array
    {
        return [
            'dateInterval' => [
                'required' => false,
            ],
        ];
    }
}
