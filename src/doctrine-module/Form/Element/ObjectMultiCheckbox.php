<?php

declare(strict_types=1);

namespace DoctrineModule\Form\Element;

use Jield\Form\Element\MultiCheckbox;
use Jield\Stdlib\ArrayUtils;
use Traversable;

use function array_map;
use function is_array;

/** @phpstan-import-type ValueOptionSpec from MultiCheckbox */
class ObjectMultiCheckbox extends MultiCheckbox
{
    use GetProxy;

    /**
     * @param array $options
     *
     * @return $this
     */
    #[\Override]
    public function setOptions(iterable $options): self
    {
        $this->getProxy()->setOptions(options: $options);

        return parent::setOptions(options: $options);
    }

    /** @return \Jield\Form\Element */
    #[\Override]
    public function setOption(string $key, mixed $value): \Jield\Form\Element
    {
        $this->getProxy()->setOptions(options: [$key => $value]);

        return parent::setOption(key: $key, value: $value);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function setValue($value): ObjectMultiCheckbox|\Jield\Form\Element\Checkbox|\Jield\Form\ElementInterface|MultiCheckbox
    {
        if ($value instanceof Traversable) {
            $value = ArrayUtils::iteratorToArray(iterator: $value);
        } elseif ($value === null) {
            return parent::setValue(value: []);
        } elseif (! is_array(value: $value)) {
            $value = (array) $value;
        }

        return parent::setValue(value: array_map(callback: [$this->getProxy(), 'getValue'], array: $value));
    }

    /**
     * {@inheritDoc}
     *
     * @return ValueOptionSpec
     */
    #[\Override]
    public function getValueOptions(): array
    {
        if ($this->valueOptions !== []) {
            return $this->valueOptions;
        }

        $proxyValueOptions = $this->getProxy()->getValueOptions();

        if (! empty($proxyValueOptions)) {
            $this->setValueOptions(options: $proxyValueOptions);
        }

        return $this->valueOptions;
    }
}
