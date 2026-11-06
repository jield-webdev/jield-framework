<?php

declare(strict_types=1);

namespace Jield\Form;

use Laminas\InputFilter\InputFilterInterface;

/** An object that supplies the input filter used when it is bound to a form. */
interface InputFilterAwareInterface
{
    public function getInputFilter(): InputFilterInterface;
}
