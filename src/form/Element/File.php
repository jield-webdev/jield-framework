<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element;
use Jield\Form\ElementPrepareAwareInterface;
use Jield\Form\FormInterface;
use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\InputProviderInterface;

/** @final */
class File extends Element implements InputProviderInterface, ElementPrepareAwareInterface
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'file',
    ];

    /**
     * Prepare the form element (mostly used for rendering purposes)
     */
    public function prepareElement(FormInterface $form): void
    {
        // Ensure the form is using correct enctype
        $form->setAttribute(key: 'enctype', value: 'multipart/form-data');
    }

    /**
     * @inheritDoc
     */
    public function getInputSpecification(): array
    {
        $spec = [
            'type'     => FileInput::class,
            'required' => false,
        ];

        $name = $this->getName();
        if ($name !== null) {
            $spec['name'] = $name;
        }

        return $spec;
    }
}
