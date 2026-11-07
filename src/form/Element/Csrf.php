<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element;
use Jield\Form\ElementPrepareAwareInterface;
use Jield\Form\FormInterface;
use Jield\Stdlib\Value;
use Jield\Validator\Options;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Session\Container;
use Laminas\Session\Validator\Csrf as CsrfValidator;

use function array_merge;
use function assert;

/**
 * @final
 * @psalm-import-type OptionsArgument from CsrfValidator
 */
class Csrf extends Element implements InputProviderInterface, ElementPrepareAwareInterface
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'hidden',
    ];

    /** @var array */
    protected array $csrfValidatorOptions = [];

    /** @var null|CsrfValidator */
    protected ?CsrfValidator $csrfValidator = null;

    /**
     * Accepted options for Csrf:
     * - csrf_options: an array used in the Csrf
     *
     * @return static
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['csrf_options'])) {
            $this->setCsrfValidatorOptions(options: Value::array($this->options['csrf_options']));
        }

        return $this;
    }

    public function getCsrfValidatorOptions(): array
    {
        return $this->csrfValidatorOptions;
    }

    /**
     * @return static
     */
    public function setCsrfValidatorOptions(array $options): static
    {
        $this->csrfValidatorOptions = $options;
        return $this;
    }

    /**
     * Get CSRF validator
     */
    public function getCsrfValidator(): CsrfValidator
    {
        if (null === $this->csrfValidator) {
            $csrfOptions = $this->getCsrfValidatorOptions();
            $csrfOptions = array_merge(['name' => Value::nonEmptyString($this->getName())], $csrfOptions);
            $csrfOptions = Options::normalize($csrfOptions);
            foreach (['name', 'hash', 'salt'] as $key) {
                if (isset($csrfOptions[$key])) {
                    $csrfOptions[$key] = Value::nonEmptyString($csrfOptions[$key]);
                }
            }
            if (isset($csrfOptions['timeout'])) {
                $csrfOptions['timeout'] = Value::positiveInt($csrfOptions['timeout']);
            }
            if (isset($csrfOptions['session'])) {
                $csrfOptions['session'] = Value::instance($csrfOptions['session'], Container::class);
            }
            /** @var OptionsArgument $csrfOptions */
            $this->setCsrfValidator(validator: new CsrfValidator($csrfOptions));
            assert(assertion: null !== $this->csrfValidator);
        }
        return $this->csrfValidator;
    }

    /**
     * @return static
     */
    public function setCsrfValidator(CsrfValidator $validator): static
    {
        $this->csrfValidator = $validator;
        return $this;
    }

    /**
     * Retrieve value
     *
     * Retrieves the hash from the validator
     */
    public function getValue(): string
    {
        $validator = $this->getCsrfValidator();
        return $validator->getHash();
    }

    /**
     * Override: get attributes
     *
     * Seeds 'value' attribute with validator hash
     *
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        $attributes = parent::getAttributes();
        $validator = $this->getCsrfValidator();
        $attributes['value'] = $validator->getHash();
        return $attributes;
    }

    /**
     * Provide default input rules for this element
     *
     * Attaches the captcha as a validator.
     *
     * @inheritDoc
     */
    public function getInputSpecification(): array
    {
        $spec = [
            'required' => true,
            'filters' => [
                ['name' => StringTrim::class],
            ],
            'validators' => [
                $this->getCsrfValidator(),
            ],
        ];

        $name = $this->getName();
        if ($name !== null) {
            $spec['name'] = $name;
        }

        return $spec;
    }

    /**
     * Prepare the form element
     */
    public function prepareElement(FormInterface $form): void
    {
        $this->getCsrfValidator()->getHash();
    }
}
