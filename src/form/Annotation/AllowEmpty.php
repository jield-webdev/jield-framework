<?php

declare(strict_types=1);

namespace Jield\Form\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Laminas\Filter\Boolean as BooleanFilter;

use function is_bool;

/**
 * AllowEmpty annotation
 *
 * Presence of this annotation is a hint that the associated
 * \Laminas\InputFilter\Input should enable the allowEmpty flag.
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute]
final class AllowEmpty
{
    private readonly bool $allowEmpty;

    /**
     * Receive and process the contents of an annotation
     *
     * @param bool|string $allowEmpty
     */
    public function __construct($allowEmpty = true)
    {
        if (!is_bool($allowEmpty)) {
            $filter = new BooleanFilter();
            $allowEmpty = $filter->filter($allowEmpty);
        }

        $this->allowEmpty = \Jield\Stdlib\Value::bool($allowEmpty);
    }

    /**
     * Get value of required flag
     */
    public function getAllowEmpty(): bool
    {
        return $this->allowEmpty;
    }
}
