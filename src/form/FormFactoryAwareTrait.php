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
     * @return mixed
     */
    public function setFormFactory(Factory $factory): mixed
    {
        $this->factory = $factory;

        return $this;
    }
}
