<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;

/** @final */
class FormWeek extends AbstractFormDateTime
{
    /**
     * Determine input type to use
     */
    protected function getType(ElementInterface $element): string
    {
        return 'week';
    }
}
