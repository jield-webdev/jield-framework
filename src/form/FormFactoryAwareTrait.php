<?php

declare(strict_types=1);

namespace Jield\Form;

trait FormFactoryAwareTrait
{
    protected ?Factory $factory = null;

    /**
     * Compose a form factory into the object
     */
    public function setFormFactory(Factory $formFactory): static
    {
        $this->factory = $formFactory;

        return $this;
    }
}
