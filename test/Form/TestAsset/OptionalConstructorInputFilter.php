<?php

declare(strict_types=1);

namespace JieldTest\Form\TestAsset;

use Jield\Form\InputFilterFactory;
use Laminas\InputFilter\InputFilter;

final class OptionalConstructorInputFilter extends InputFilter
{
    public function __construct(array $options = [])
    {
        parent::__construct(InputFilterFactory::create());
    }
}
