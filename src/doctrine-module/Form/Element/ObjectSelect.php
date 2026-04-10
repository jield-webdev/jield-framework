<?php

declare(strict_types=1);

namespace DoctrineModule\Form\Element;

use Jield\Form\Element\Select as SelectElement;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function array_map;
use function is_array;

class ObjectSelect extends SelectElement
{
    use GetProxy;

    /**
     * @param array $options
     *
     * @return SelectElement
     */
    #[\Override]
    public function setOptions(iterable $options): SelectElement
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
    public function setValue($value): \Jield\Form\ElementInterface|ObjectSelect
    {
        $multiple = $this->getAttribute(key: 'multiple');

        if ($multiple === true || $multiple === 'multiple') {
            if ($value instanceof Traversable) {
                $value = ArrayUtils::iteratorToArray(iterator: $value);
            } elseif ($value === null) {
                return parent::setValue(value: []);
            } elseif (! is_array(value: $value)) {
                $value = (array) $value;
            }

            return parent::setValue(value: array_map(callback: [$this->getProxy(), 'getValue'], array: $value));
        }

        return parent::setValue(value: $this->getProxy()->getValue(value: $value));
    }

    /**
     * {@inheritDoc}
     *
     * @return array<array-key,mixed>
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
