<?php

declare(strict_types=1);

namespace Jield\Form\Annotation;

use ArrayAccess;
use Jield\Form\Element\Collection;
use Jield\Form\Fieldset;
use Jield\Form\InputFilterProviderFieldset;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\InputFilter\InputFilter;
use Laminas\Stdlib\ArrayObject;
use Webmozart\Assert\Assert;

use function array_merge;
use function assert;
use function is_array;

/**
 * Default listeners for element annotations
 *
 * Defines and attaches a set of default listeners for element annotations
 * (which are defined on object properties). These include:
 *
 * - AllowEmpty
 * - Attributes
 * - ErrorMessage
 * - Filter
 * - Flags
 * - Input
 * - Hydrator
 * - Object and Instance (the latter is preferred starting in 2.4)
 * - Required
 * - Type
 * - Validator
 *
 * See the individual annotation classes for more details. The handlers registered
 * work with the annotation values, as well as the element and input specification
 * passed in the event object.
 */
final class ElementAnnotationsListener extends AbstractAnnotationsListener
{
    /**
     * phpcs:disable Generic.Files.LineLength
     *
     * @inheritDoc
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach('configureElement', $this->handleAllowEmptyAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleAttributesAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleComposedObjectAnnotation(...), $priority);
        $this->listeners[] = $events->attach(
            'configureElement',
            $this->handleContinueIfEmptyAnnotation(...),
            $priority,
        );
        $this->listeners[] = $events->attach('configureElement', $this->handleErrorMessageAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleFilterAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleFlagsAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleHydratorAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleInputAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleInstanceAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleOptionsAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleRequiredAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleTypeAnnotation(...), $priority);
        $this->listeners[] = $events->attach('configureElement', $this->handleValidatorAnnotation(...), $priority);

        $this->listeners[] = $events->attach('discoverName', $this->handleNameAnnotation(...), $priority);
        $this->listeners[] = $events->attach('discoverName', $this->discoverFallbackName(...), $priority);

        $this->listeners[] = $events->attach('checkForExclude', $this->handleExcludeAnnotation(...), $priority);
    }

    /**
     * Handle the AllowEmpty annotation
     *
     * Sets the allow_empty flag on the input specification array.
     */
    public function handleAllowEmptyAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof AllowEmpty) {
            return;
        }

        /** @mago-var mixed $inputSpec */
        $inputSpec = $e->getParam('inputSpec');
        Value::setPath($inputSpec, ['allow_empty'], true);
    }

    /**
     * Handle the Attributes annotation
     *
     * Sets the attributes array of the element specification.
     */
    public function handleAttributesAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Attributes) {
            return;
        }

        /** @mago-var mixed $elementSpec */
        $elementSpec = $e->getParam('elementSpec');
        if (isset($elementSpec['spec']['attributes'])) {
            $elementSpec['spec']['attributes'] = array_merge(
                Assert::isArray($elementSpec['spec']['attributes']),
                $annotation->getAttributes(),
            );
            return;
        }

        Value::setPath($elementSpec, ['spec', 'attributes'], $annotation->getAttributes());
    }

    /**
     * Allow creating fieldsets from composed entity properties
     */
    public function handleComposedObjectAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof ComposedObject) {
            return;
        }

        $class = $annotation->getComposedObject();
        $annotationManager = $e->getTarget();
        /** @mago-var mixed $specification */
        $specification = Value::call(Assert::object($annotationManager), 'getFormSpecification', [$class]);

        /** @mago-var mixed $name */
        $name = $e->getParam('name');
        /** @mago-var mixed $elementSpec */
        $elementSpec = $e->getParam('elementSpec');

        if ($annotation->isCollection()) {
            // Compose specification as a fieldset into parent form/fieldset
            if (!isset($specification['type'])) {
                //use input filter provider fieldset so we can compose the input filter into the fieldset
                //it is assumed that if someone uses a custom fieldset, they will take care of the input
                //filtering themselves or consume the input_filter_spec option.
                Value::setPath($specification, ['type'], InputFilterProviderFieldset::class);
            }

            /** @mago-var mixed $inputFilter */
            $inputFilter = $specification['input_filter'];
            if (!isset($inputFilter['type'])) {
                Value::setPath($inputFilter, ['type'], InputFilter::class);
            }
            unset($specification['input_filter']);

            Value::setPath($elementSpec, ['spec', 'type'], Collection::class);
            Value::setPath($elementSpec, ['spec', 'name'], $name);

            /** @mago-var mixed $targetElementOptions */
            $targetElementOptions = $specification['options'] ?? [];
            assert(is_array($targetElementOptions));
            $targetElementOptions['input_filter_spec'] = $inputFilter;
            $targetElementOptions['target_type'] = $class;
            Value::setPath($specification, ['options'], $targetElementOptions);

            Value::setPath(
                $elementSpec,
                ['spec', 'options'],
                new ArrayObject($this->mergeOptions($elementSpec, $annotation)),
            );

            Value::setPath($elementSpec, ['spec', 'options', 'target_element'], $specification);

            if (isset($specification['hydrator'])) {
                Value::setPath($elementSpec, ['spec', 'hydrator'], $specification['hydrator']);
            }
        } else {
            // Compose input filter into parent input filter
            $specification = Assert::isArray($specification);
            /** @mago-var mixed $inputFilter */
            $inputFilter = $specification['input_filter'];
            if (!isset($inputFilter['type'])) {
                Value::setPath($inputFilter, ['type'], InputFilter::class);
            }
            /** @mago-var mixed $inputSpec */
            $inputSpec = $e->getParam('inputSpec');
            Value::call($inputSpec, 'exchangeArray', [
                $inputFilter instanceof \ArrayObject ? $inputFilter->getArrayCopy() : Value::toArray($inputFilter),
            ]);
            unset($specification['input_filter']);

            // Compose specification as a fieldset into parent form/fieldset
            if (!isset($specification['type'])) {
                Value::setPath($specification, ['type'], Fieldset::class);
            }

            if (isset($elementSpec['spec']['options'])) {
                $specification['options'] ??= [];
                Value::setPath(
                    $specification,
                    ['options'],
                    array_merge(Assert::isArray($elementSpec['spec']['options']), Assert::isArray($specification['options'])),
                );
            }

            // Add element spec:
            Value::setPath($elementSpec, ['spec'], $specification);
            Value::setPath($elementSpec, ['spec', 'name'], $name);
            Value::setPath(
                $elementSpec,
                ['spec', 'options'],
                new ArrayObject($this->mergeOptions($elementSpec, $annotation)),
            );
        }
    }

    /**
     * Handle the ContinueIfEmpty annotation
     *
     * Sets the continue_if_empty flag on the input specification array.
     */
    public function handleContinueIfEmptyAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof ContinueIfEmpty) {
            return;
        }

        /** @mago-var mixed $inputSpec */
        $inputSpec = $e->getParam('inputSpec');
        Value::setPath($inputSpec, ['continue_if_empty'], true);
    }

    /**
     * Handle the ErrorMessage annotation
     *
     * Sets the error_message of the input specification.
     */
    public function handleErrorMessageAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof ErrorMessage) {
            return;
        }

        /** @mago-var mixed $inputSpec */
        $inputSpec = $e->getParam('inputSpec');
        Value::setPath($inputSpec, ['error_message'], $annotation->getMessage());
    }

    /**
     * Determine if the element has been marked to exclude from the definition
     */
    public function handleExcludeAnnotation(EventInterface $e): bool
    {
        /** @mago-var mixed $annotations */
        $annotations = $e->getParam('annotations');
        assert($annotations instanceof AnnotationCollection);

        if ($annotations->hasAnnotation(Exclude::class)) {
            return true;
        }
        return false;
    }

    /**
     * Handle the Filter annotation
     *
     * Adds a filter to the filter chain specification for the input.
     */
    public function handleFilterAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Filter) {
            return;
        }

        /** @mago-var mixed $inputSpec */
        $inputSpec = $e->getParam('inputSpec');
        if (!isset($inputSpec['filters'])) {
            Value::setPath($inputSpec, ['filters'], []);
        }
        Value::setPath($inputSpec, ['filters', null], $annotation->getFilterSpecification());
    }

    /**
     * Handle the Flags annotation
     *
     * Sets the element flags in the specification (used typically for setting
     * priority).
     */
    public function handleFlagsAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Flags) {
            return;
        }

        /** @mago-var mixed $elementSpec */
        $elementSpec = $e->getParam('elementSpec');
        Value::setPath($elementSpec, ['flags'], $annotation->getFlags());
    }

    /**
     * Handle the Hydrator annotation
     *
     * Sets the hydrator class to use in the fieldset specification.
     */
    public function handleHydratorAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Hydrator) {
            return;
        }

        /** @mago-var mixed $elementSpec */
        $elementSpec = $e->getParam('elementSpec');
        Value::setPath($elementSpec, ['spec', 'hydrator'], $annotation->getHydratorSpecification());
    }

    /**
     * Handle the Input annotation
     *
     * Sets the filter specification for the current element to the specified
     * input class name.
     */
    public function handleInputAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Input) {
            return;
        }

        /** @mago-var mixed $inputSpec */
        $inputSpec = $e->getParam('inputSpec');
        Value::setPath($inputSpec, ['type'], $annotation->getInput());
    }

    /**
     * Handle the Instance annotations
     *
     * Sets the object to bind to the form or fieldset
     */
    public function handleInstanceAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Instance) {
            return;
        }

        /** @mago-var mixed $elementSpec */
        $elementSpec = $e->getParam('elementSpec');
        Value::setPath($elementSpec, ['spec', 'object'], $annotation->getInstance());
    }

    /**
     * Handle the Options annotation
     *
     * Sets the element options in the specification.
     */
    public function handleOptionsAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Options) {
            return;
        }

        /** @mago-var mixed $elementSpec */
        $elementSpec = $e->getParam('elementSpec');
        Value::setPath($elementSpec, ['spec', 'options'], $this->mergeOptions(Value::accessible($elementSpec), $annotation));
    }

    /**
     * Handle the Required annotation
     *
     * Sets the required flag on the input based on the annotation value.
     */
    public function handleRequiredAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Required) {
            return;
        }

        $required = $annotation->getRequired();
        /** @mago-var mixed $inputSpec */
        $inputSpec = $e->getParam('inputSpec');
        Value::setPath($inputSpec, ['required'], $required);

        if ($required) {
            /** @mago-var mixed $elementSpec */
            $elementSpec = $e->getParam('elementSpec');
            if (!isset($elementSpec['spec']['attributes'])) {
                Value::setPath($elementSpec, ['spec', 'attributes'], []);
            }

            Value::setPath($elementSpec, ['spec', 'attributes', 'required'], 'required');
        }
    }

    /**
     * Handle the Type annotation
     *
     * Sets the element class type to use in the element specification.
     */
    public function handleTypeAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Type) {
            return;
        }

        /** @mago-var mixed $elementSpec */
        $elementSpec = $e->getParam('elementSpec');
        Value::setPath($elementSpec, ['spec', 'type'], $annotation->getType());
    }

    /**
     * Handle the Validator annotation
     *
     * Adds a validator to the validator chain of the input specification.
     */
    public function handleValidatorAnnotation(EventInterface $e): void
    {
        /** @mago-var mixed $annotation */
        $annotation = $e->getParam('annotation');
        if (!$annotation instanceof Validator) {
            return;
        }

        /** @mago-var mixed $inputSpec */
        $inputSpec = $e->getParam('inputSpec');
        if (!isset($inputSpec['validators'])) {
            Value::setPath($inputSpec, ['validators'], []);
        }
        Value::setPath($inputSpec, ['validators', null], $annotation->getValidatorSpecification());
    }

    /**
     * @param array|ArrayAccess      $elementSpec
     * @param ComposedObject|Options $annotation
     */
    private function mergeOptions($elementSpec, $annotation): array
    {
        if (isset($elementSpec['spec']['options'])) {
            if (is_array($elementSpec['spec']['options'])) {
                return array_merge($elementSpec['spec']['options'], $annotation->getOptions());
            }

            if ($elementSpec['spec']['options'] instanceof ArrayObject) {
                return array_merge($elementSpec['spec']['options']->getArrayCopy(), $annotation->getOptions());
            }
        }

        return $annotation->getOptions();
    }
}
