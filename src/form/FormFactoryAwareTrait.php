<?php

declare(strict_types=1);

namespace Jield\Form;

/** @psalm-require-implements FormFactoryAwareInterface */
trait FormFactoryAwareTrait
{
    protected ?Factory $factory = null;

    /**
     * Compose a form factory into the object
     *
     * @param Factory $formFactory
     * @return FormFactoryAwareInterface
     */
    public function setFormFactory(Factory $formFactory): FormFactoryAwareInterface
    {
        $this->factory = $formFactory;

        return $this;
    }
}
