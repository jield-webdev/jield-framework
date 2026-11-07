<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element;
use Jield\Form\ElementPrepareAwareInterface;
use Jield\Form\FormInterface;
use Jield\Validator\Options;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Session\Container;
use Laminas\Session\Validator\Csrf as CsrfValidator;
use Webmozart\Assert\Assert;

use function array_merge;

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

    protected array $csrfValidatorOptions = [];

    protected ?CsrfValidator $csrfValidator = null;

    /**
     * Accepted options for Csrf:
     * - csrf_options: an array used in the Csrf
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (($this->options['csrf_options'] ?? null) !== null) {
            $this->setCsrfValidatorOptions(options: Assert::isArray($this->options['csrf_options']));
        }

        return $this;
    }

    public function getCsrfValidatorOptions(): array
    {
        return $this->csrfValidatorOptions;
    }

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
            $csrfOptions = array_merge(['name' => Assert::stringNotEmpty($this->getName())], $csrfOptions);
            $csrfOptions = Options::normalize($csrfOptions);
            foreach (['name', 'hash', 'salt'] as $key) {
                if (($csrfOptions[$key] ?? null) === null) {
                    continue;
                }

                $csrfOptions[$key] = Assert::stringNotEmpty($csrfOptions[$key]);
            }
            if (($csrfOptions['timeout'] ?? null) !== null) {
                $csrfOptions['timeout'] = Assert::positiveInteger($csrfOptions['timeout']);
            }
            if (($csrfOptions['session'] ?? null) !== null) {
                $csrfOptions['session'] = Assert::isInstanceOf($csrfOptions['session'], Container::class);
            }
            /** @var OptionsArgument $csrfOptions */
            $this->csrfValidator = new CsrfValidator($csrfOptions);
        }
        return $this->csrfValidator;
    }

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
