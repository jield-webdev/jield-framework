<?php

declare(strict_types=1);

namespace DoctrineModule\Form\Element;

trait GetProxy
{
    protected ?Proxy $proxy = null;

    public function getProxy(): Proxy
    {
        $this->proxy ??= new Proxy();

        return $this->proxy;
    }
}
