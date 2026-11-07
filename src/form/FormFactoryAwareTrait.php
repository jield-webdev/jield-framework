<?php

declare(strict_types=1);

namespace Jield\Form;

trait FormFactoryAwareTrait
{
    /** @var null|Factory */
    protected ?Factory $factory = null;

    /**
     * Compose a form factory into the object
     *
     * @return static
     */
    public function setFormFactory(Factory $formFactory): static
    {
        $this->factory = $formFactory;

        return $this;
    }
}
