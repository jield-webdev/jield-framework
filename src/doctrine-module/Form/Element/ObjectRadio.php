<?php

declare(strict_types=1);

namespace DoctrineModule\Form\Element;

use Jield\Form\Element\MultiCheckbox;
use Jield\Form\Element\Radio as RadioElement;
use Jield\Stdlib\Value;
use Override;

/** @phpstan-import-type ValueOptionSpec from MultiCheckbox */
class ObjectRadio extends RadioElement
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
        return parent::setValue(value: $this->getProxy()->getValue(value: $value));
    }

    /**
     * {@inheritDoc}
     *
     * @return ValueOptionSpec
     */
    #[Override]
    public function getValueOptions(): array
    {
        if ($this->valueOptions !== []) {
            return $this->valueOptions;
        }

        /** @var ValueOptionSpec $proxyValueOptions */
        $proxyValueOptions = $this->getProxy()->getValueOptions();

        if (!empty($proxyValueOptions)) {
            $this->setValueOptions(options: $proxyValueOptions);
        }

        return $this->valueOptions;
    }
}
