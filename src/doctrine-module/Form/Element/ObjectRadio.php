<?php

declare(strict_types=1);

namespace DoctrineModule\Form\Element;

use Jield\Form\Element\MultiCheckbox;
use Jield\Form\Element\Radio as RadioElement;

/** @phpstan-import-type ValueOptionSpec from MultiCheckbox */
class ObjectRadio extends RadioElement
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
    public function setValue($value): \Jield\Form\Element\Checkbox|ObjectRadio|\Jield\Form\ElementInterface|MultiCheckbox
    {
        return parent::setValue(value: $this->getProxy()->getValue(value: $value));
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
