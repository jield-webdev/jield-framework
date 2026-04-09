<?php

declare(strict_types=1);

namespace Jield\Form;

interface FormFactoryAwareInterface
{
    /**
     * Compose a form factory into the object
     *
     * @return self
     */
    public function setFormFactory(Factory $formFactory): FormFactoryAwareInterface;
}
