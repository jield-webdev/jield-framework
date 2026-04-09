<?php

declare(strict_types=1);

namespace Jield\Form;

use Laminas\InputFilter\InputFilterProviderInterface;
use Jield\Stdlib\ArrayUtils;
use Traversable;

final class InputFilterProviderFieldset extends Fieldset implements InputFilterProviderInterface
{
    /**
     * Holds the specification which will be returned by getInputFilterSpecification
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

        $this->filterSpec = $filterSpec;
    }

    /**
     * Set options for a fieldset. Accepted options are:
     * - input_filter_spec: specification to be returned by getInputFilterSpecification
     *
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['input_filter_spec'])) {
            $this->setInputFilterSpecification(filterSpec: $this->options['input_filter_spec']);
        }

        return $this;
    }
}
