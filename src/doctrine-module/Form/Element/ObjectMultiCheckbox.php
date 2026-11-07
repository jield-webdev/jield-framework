<?php

declare(strict_types=1);

namespace DoctrineModule\Form\Element;

use Jield\Form\Element\MultiCheckbox;
use Jield\Stdlib\Value;
use Laminas\Stdlib\ArrayUtils;
use Override;
use Traversable;
use Webmozart\Assert\Assert;

use function array_map;
use function is_array;

/** @phpstan-import-type ValueOptionSpec from MultiCheckbox */
class ObjectMultiCheckbox extends MultiCheckbox
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
        if ($value instanceof Traversable) {
            $value = ArrayUtils::iteratorToArray(iterator: $value);
            return parent::setValue(value: array_map(
                callback: [$this->getProxy(), 'getValue'],
                array: Assert::isArray($value),
            ));
        }
        if ($value === null) {
            return parent::setValue(value: []);
        }
        if (!is_array(value: $value)) {
            $value = Value::toArray($value);
            return parent::setValue(value: array_map(
                callback: [$this->getProxy(), 'getValue'],
                array: Assert::isArray($value),
            ));
        }
        return parent::setValue(value: array_map(
            callback: [$this->getProxy(), 'getValue'],
            array: Assert::isArray($value),
        ));
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
