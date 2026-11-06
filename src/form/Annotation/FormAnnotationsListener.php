<?php

declare(strict_types=1);

namespace Jield\Form\Annotation;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManagerInterface;

/**
 * Default listeners for form annotations
 *
 * Defines and attaches a set of default listeners for form annotations
 * (which are defined on object properties). These include:
 *
 * - Attributes
 * - Flags
 * - Hydrator
 * - Object and Instance (the latter is preferred starting in 2.4)
 * - InputFilter
 * - Type
 * - ValidationGroup
 *
 * See the individual annotation classes for more details. The handlers
 * registered work with the annotation values, as well as the form
 * specification passed in the event object.
 */
final class FormAnnotationsListener extends AbstractAnnotationsListener
{
    /**
     * Attach listeners
     *
     * @inheritDoc
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach('configureForm', $this->handleAttributesAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureForm', $this->handleFlagsAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureForm', $this->handleHydratorAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureForm', $this->handleInputFilterAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureForm', $this->handleInstanceAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureForm', $this->handleOptionsAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureForm', $this->handleTypeAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureForm', $this->handleValidationGroupAnnotation(...), $priority);

        $this->listeners[] = $events->attach('discoverName', $this->handleNameAnnotation(...), $priority);
        $this->listeners[] = $events->attach('discoverName', $this->discoverFallbackName(...), $priority);
    }

    /**
     * Handle the Attributes annotation
     *
     * Sets the attributes key of the form specification.
     */
    public function handleAttributesAnnotation(EventInterface $e): void
    {
        /** @var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Attributes) {
            return;
        }

        /** @var mixed $formSpec */
        $formSpec = $e->getParam('formSpec');
        \Jield\Stdlib\Value::setPath($formSpec, ['attributes'], $annotation->getAttributes());
    }

    /**
     * Handle the Flags annotation
     *
     * Sets the flags key of the form specification.
     */
    public function handleFlagsAnnotation(EventInterface $e): void
    {
        /** @var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Flags) {
            return;
        }

        /** @var mixed $formSpec */
        $formSpec = $e->getParam('formSpec');
        \Jield\Stdlib\Value::setPath($formSpec, ['flags'], $annotation->getFlags());
    }

    /**
     * Handle the Hydrator annotation
     *
     * Sets the hydrator class to use in the form specification.
     */
    public function handleHydratorAnnotation(EventInterface $e): void
    {
        /** @var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Hydrator) {
            return;
        }

        /** @var mixed $formSpec */
        $formSpec = $e->getParam('formSpec');
        \Jield\Stdlib\Value::setPath($formSpec, ['hydrator'], $annotation->getHydratorSpecification());
    }

    /**
     * Handle the InputFilter annotation
     *
     * Sets the input filter class to use in the form specification.
     */
    public function handleInputFilterAnnotation(EventInterface $e): void
    {
        /** @var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof InputFilter) {
            return;
        }

        /** @var mixed $formSpec */
        $formSpec = $e->getParam('formSpec');
        \Jield\Stdlib\Value::setPath($formSpec, ['input_filter'], $annotation->getInputFilter());
    }

    /**
     * Handle the Instance annotations
     *
     * Sets the object to bind to the form or fieldset
     */
    public function handleInstanceAnnotation(EventInterface $e): void
    {
        /** @var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Instance) {
            return;
        }

        /** @var mixed $formSpec */
        $formSpec = $e->getParam('formSpec');
        \Jield\Stdlib\Value::setPath($formSpec, ['object'], $annotation->getInstance());
    }

    /**
     * Handle the Options annotation
     *
     * Sets the options key of the form specification.
     */
    public function handleOptionsAnnotation(EventInterface $e): void
    {
        /** @var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Options) {
            return;
        }

        /** @var mixed $formSpec */
        $formSpec = $e->getParam('formSpec');
        \Jield\Stdlib\Value::setPath($formSpec, ['options'], $annotation->getOptions());
    }

    /**
     * Handle the Type annotation
     *
     * Sets the form class to use in the form specification.
     */
    public function handleTypeAnnotation(EventInterface $e): void
    {
        /** @var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Type) {
            return;
        }

        /** @var mixed $formSpec */
        $formSpec = $e->getParam('formSpec');
        \Jield\Stdlib\Value::setPath($formSpec, ['type'], $annotation->getType());
    }

    /**
     * Handle the ValidationGroup annotation
     *
     * Sets the validation group to use in the form specification.
     */
    public function handleValidationGroupAnnotation(EventInterface $e): void
    {
        /** @var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof ValidationGroup) {
            return;
        }

        /** @var mixed $formSpec */
        $formSpec = $e->getParam('formSpec');
        \Jield\Stdlib\Value::setPath($formSpec, ['validation_group'], $annotation->getValidationGroup());
    }
}
