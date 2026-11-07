<?php

declare(strict_types=1);

namespace Jield\Form;

use function array_key_exists;

trait LabelAwareTrait
{
    /**
     * Label specific html attributes
     *
     * @var array<string, scalar|null>
     */
    protected array $labelAttributes = [];

    /**
     * Label specific options
     *
     * @var array<string, mixed>
     */
    protected array $labelOptions = [];

    /**
     * Set the attributes to use with the label
     */
    public function setLabelAttributes(array $labelAttributes): static
    {
        $this->labelAttributes = \Jield\Stdlib\Value::scalarMap($labelAttributes);
        return $this;
    }

    /**
     * Get the attributes to use with the label
     */
    public function getLabelAttributes(): array
    {
        return $this->labelAttributes;
    }

    /**
     * Set many label options at once
     *
     * Implementation will decide if this will overwrite or merge.
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setLabelOptions(iterable $arrayOrTraversable): static
    {
        /**
         * @mago-var mixed $key
         * @mago-var mixed $value
         */
        foreach ($arrayOrTraversable as $key => $value) {
            $this->setLabelOption(key: \Webmozart\Assert\Assert::string($key), value: $value);
        }
        return $this;
    }

    /**
     * Get label specific options
     */
    public function getLabelOptions(): array
    {
        return $this->labelOptions;
    }

    /**
     * Clear all label options
     */
    public function clearLabelOptions(): static
    {
        $this->labelOptions = [];
        return $this;
    }

    /**
     * Remove many attributes at once
     */
    public function removeLabelOptions(array $keys): static
    {
        /** @mago-var mixed $key */
        foreach ($keys as $key) {
            unset($this->labelOptions[$key]);
        }

        return $this;
    }

    /**
     * Set a single label optionn
     */
    public function setLabelOption(string $key, mixed $value): static
    {
        $this->labelOptions[$key] = $value;
        return $this;
    }

    /**
     * Retrieve a single label option
     */
    public function getLabelOption(string $key): mixed
    {
        if (!array_key_exists(key: $key, array: $this->labelOptions)) {
            return null;
        }
        return $this->labelOptions[$key];
    }

    /**
     * Remove a single label option
     */
    public function removeLabelOption(string $key): static
    {
        unset($this->labelOptions[$key]);
        return $this;
    }

    /**
     * Does the element has a specific label option ?
     */
    public function hasLabelOption(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->labelOptions);
    }
}
