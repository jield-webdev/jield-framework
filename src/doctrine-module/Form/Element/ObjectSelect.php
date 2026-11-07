<?php

declare(strict_types=1);

namespace DoctrineModule\Form\Element;

use Jield\Form\Element\Select as SelectElement;
use Jield\Stdlib\Value;
use Laminas\Stdlib\ArrayUtils;
use Override;
use Traversable;

use function array_map;
use function is_array;

/** @psalm-import-type ValueOptions from \Jield\Form\Element\Select */
class ObjectSelect extends SelectElement
{
    use GetProxy;

    /**
     * @param iterable $options
     *
     * @return static
     */
    #[Override]
    public function setOptions(iterable $options): static
    {
        $this->getProxy()->setOptions(options: Value::array($options));

        return parent::setOptions(options: $options);
    }

    /** @return $this */
    #[Override]
    public function setOption(string $key, mixed $value): static
    {
        $this->getProxy()->setOptions(options: [$key => $value]);

        parent::setOption(key: $key, value: $value);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function setValue($value): static
    {
        $multiple = $this->getAttribute(key: 'multiple');

        if ($multiple === true || $multiple === 'multiple') {
            if ($value instanceof Traversable) {
                $value = ArrayUtils::iteratorToArray(iterator: $value);
            } elseif ($value === null) {
                return parent::setValue(value: []);
            } elseif (!is_array(value: $value)) {
                $value = Value::toArray($value);
            }

            return parent::setValue(value: array_map(
                callback: [$this->getProxy(), 'getValue'],
                array: Value::array($value),
            ));
        }

        return parent::setValue(value: $this->getProxy()->getValue(value: $value));
    }

    /**
     * {@inheritDoc}
     *
     * @return ValueOptions
     */
    #[Override]
    public function getValueOptions(): array
    {
        if ($this->valueOptions !== []) {
            return $this->valueOptions;
        }

        /** @var ValueOptions $proxyValueOptions */
        $proxyValueOptions = $this->getProxy()->getValueOptions();

        if (!empty($proxyValueOptions)) {
            $this->setValueOptions(options: $proxyValueOptions);
        }

        return $this->valueOptions;
    }
}
