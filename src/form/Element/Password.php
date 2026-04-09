<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element;
use Jield\Form\ElementPrepareAwareInterface;
use Jield\Form\FormInterface;

/** @final */
class Password extends Element implements ElementPrepareAwareInterface
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'password',
    ];

    /**
     * Remove the password before rendering if the form fails in order to avoid any security issue
     */
    public function prepareElement(FormInterface $form): void
    {
        $this->setValue(value: '');
    }
}
