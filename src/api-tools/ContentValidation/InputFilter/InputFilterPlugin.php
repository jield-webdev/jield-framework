<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentValidation\InputFilter;

use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Jield\Mvc\InjectApplicationEventInterface;
use Laminas\InputFilter\InputFilterInterface;

class InputFilterPlugin extends AbstractPlugin
{
    /** @return null|InputFilterInterface */
    public function __invoke(): ?InputFilterInterface
    {
        $controller = $this->getController();
        if (!$controller instanceof InjectApplicationEventInterface) {
            return null;
        }

        $event = $controller->getEvent();
        $inputFilter = $event->getParam(name: __NAMESPACE__);

        if (!$inputFilter instanceof InputFilterInterface) {
            return null;
        }

        return $inputFilter;
    }
}
