<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper\File;

use Jield\Form\ElementInterface;
use Jield\Form\View\Helper\FormInput;

use function sprintf;
use function uniqid;

/**
 * A view helper to render the hidden input with a UploadProgress id
 * for file uploads progress tracking.
 */
class FormFileUploadProgress extends FormInput
{
    /**
     * Invoke helper as functor
     *
     * Proxies to {@link render()}.
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(?ElementInterface $element = null): string
    {
        return $this->renderHiddenId();
    }

    /**
     * Render a hidden form <input> element with the progress id
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function renderHiddenId(): string
    {
        $attributes = [
            'id' => 'progress_key',
            'name' => $this->getName(),
            'type' => 'hidden',
            'value' => $this->getValue(),
        ];

        return sprintf(
            '<input %s%s',
            $this->createAttributesString(attributes: $attributes),
            $this->getInlineClosingBracket(),
        );
    }

    protected function getName(): string
    {
        return 'UPLOAD_IDENTIFIER';
    }

    protected function getValue(): string
    {
        return uniqid();
    }
}
