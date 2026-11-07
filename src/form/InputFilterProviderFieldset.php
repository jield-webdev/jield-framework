<?php

declare(strict_types=1);

namespace Jield\Form;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

/**
 * @psalm-import-type InputFilterSpecification from InputFilterInterface
 * @psalm-import-type CollectionSpecification from InputFilterInterface
 */
final class InputFilterProviderFieldset extends Fieldset implements InputFilterProviderInterface
{
    /**
     * Holds the specification which will be returned by getInputFilterSpecification
     * @var InputFilterSpecification|CollectionSpecification
     */
    private array $filterSpec = [];

    public function getInputFilterSpecification(): array
    {
        return $this->filterSpec;
    }

    public function setInputFilterSpecification(iterable $filterSpec): void
    {
        if ($filterSpec instanceof Traversable) {
            $filterSpec = ArrayUtils::iteratorToArray(iterator: $filterSpec);
        }

        /** @var InputFilterSpecification|CollectionSpecification $filterSpec */
        $this->filterSpec = $filterSpec;
    }

    /**
     * Set options for a fieldset. Accepted options are:
     * - input_filter_spec: specification to be returned by getInputFilterSpecification
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['input_filter_spec'])) {
            $this->setInputFilterSpecification(filterSpec: \Webmozart\Assert\Assert::isIterable($this->options['input_filter_spec']));
        }

        return $this;
    }
}
