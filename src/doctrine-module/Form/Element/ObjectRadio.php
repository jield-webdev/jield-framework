<?php

declare(strict_types=1);

namespace DoctrineModule\Form\Element;

use Jield\Form\Element\MultiCheckbox;
use Jield\Form\Element\Radio as RadioElement;
use Override;
use Webmozart\Assert\Assert;

/** @phpstan-import-type ValueOptionSpec from MultiCheckbox */
class ObjectRadio extends RadioElement
{
    use GetProxy;

    #[Override]
    public function setOptions(iterable $options): static
    {
        $this->getProxy()->setOptions(options: Assert::isArray($options));

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
     * @throws \RuntimeException
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
     * @throws \Exception
     */
    #[Override]
    public function getValueOptions(): array
    {
        if ($this->valueOptions !== []) {
            return $this->valueOptions;
        }

        /** @var ValueOptionSpec $proxyValueOptions */
        $proxyValueOptions = $this->getProxy()->getValueOptions();

        if (!($proxyValueOptions === [])) {
            $this->setValueOptions(options: $proxyValueOptions);
        }

        return $this->valueOptions;
    }
}
