<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element;

/** @final */
class Image extends Element
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'image',
    ];
}
