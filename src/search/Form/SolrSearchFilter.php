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
use Laminas\InputFilter\InputFilterProviderInterface;
use RuntimeException;
use Solarium\Component\Result\Facet\FacetResultInterface;

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
            /** @var mixed $values */
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

            /** @var mixed $field */
            $field = $this->getFacetByFacetField(fieldName: $facetField->getField());

            if ($facetField->getHasYesNo()) {
                $facetElementFieldset->add(elementOrFieldset: $this->createYesNoFormElement());
            }

            $facetElementFieldset->add(elementOrFieldset: $this->createFacetFieldFormElement(
                field: \Jield\Stdlib\Value::instance($field, \Solarium\Component\Result\Facet\Field::class),
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
        /** @var mixed $facetSet */
        $facetSet = $this->searchService->getResultSet()->getFacetSet();

        if (null === $facetSet) {
            throw new RuntimeException(message: 'This search has no facets');
        }

        $facetField = $this->searchService->getFacet(fieldName: $fieldName);

        return \Jield\Stdlib\Value::call($facetSet, 'getFacet', [$facetField->getField()]);
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

    private function createFacetFieldFormElement(\Solarium\Component\Result\Facet\Field $field, FacetField $facetField): MultiCheckbox
    {
        $multiOptions = [];
        /** @var mixed $value */
        foreach ($field as $key => $value) {
            $multiOptions[$key] = sprintf('%s <small class="text-muted">(%s)</small>', $key, \Jield\Stdlib\Value::scalar($value));
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
                'resetEncodedFilter' => base64_encode(string: \Jield\Stdlib\Value::string(json_encode(value: $data))),
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
                'resetEncodedFilter' => base64_encode(string: \Jield\Stdlib\Value::string(json_encode(value: $data))),
            ];
        }

        if (!empty($submittedData['filter']['general'])) {
            /** @var mixed $selectedValues */
            $selectedValues = \Jield\Stdlib\Value::scalars($submittedData['filter']['general']);
            /** @var mixed $value */
            foreach (\Jield\Stdlib\Value::iterable($selectedValues) as $value) {
                $remainingValues = array_diff(\Jield\Stdlib\Value::scalars(\Jield\Stdlib\Value::array($selectedValues)), \Jield\Stdlib\Value::scalars([$value]));

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
                    'resetEncodedFilter' => base64_encode(string: \Jield\Stdlib\Value::string(json_encode(value: $data))),
                ];
            }
        }

        /** @var mixed $facetName
         * @var mixed $facetData
         */
        foreach (\Jield\Stdlib\Value::iterable($submittedData['facet'] ?? []) as $facetName => $facetData) {
            //We need to check if the facet is actually a facet field
            if (!$this->searchService->hasFacet(fieldName: \Jield\Stdlib\Value::string($facetName))) {
                continue;
            }

            $facetField = $this->searchService->getFacet(fieldName: \Jield\Stdlib\Value::string($facetName));

            /** @var mixed $values */
            $values = $facetData['values'] ?? [];

            if (is_string(value: $values) && $facetField->isSlider()) {
                $values = array_map(callback: 'intval', array: explode(separator: ',', string: $values));
                $valueText = sprintf('BETWEEN %s and %s', $values[0], $values[1] ?? '');
            } elseif ($facetField->isCheckboxMin()) {
                $valueText = sprintf('AT LEAST %d', \Jield\Stdlib\Value::scalar($values[0] ?? ''));
            } else {
                $valueText = implode(separator: $facetData['andOr'] ?? false ? ' and ' : ' or ', array: \Jield\Stdlib\Value::scalars($values));
            }

            //Remaining facets are all facets where the current facet value is filtered out
            /** @var mixed $remainingFacets */
            $remainingFacets = $submittedData['facet'];

            $remainingFacets = \Jield\Stdlib\Value::accessible($remainingFacets);

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
