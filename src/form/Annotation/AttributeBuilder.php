<?php

declare(strict_types=1);

namespace Jield\Form\Annotation;

use ArrayObject;
use Jield\Form\Exception;
use ReflectionClass;
use ReflectionException;

use function sprintf;

use const PHP_MAJOR_VERSION;
use const PHP_VERSION;

/**
 * Parses the properties of a class for PHP attributes in order to create a form
 * and input filter definition.
 */
final class AttributeBuilder extends AbstractBuilder
{
    /**
     * Initialize the attribute builder
     */
    public function __construct() {}

    /**
     * Derive a form specification from PHP attributes for a given entity
     *
     * @param  object|class-string $entity
     * @throws ReflectionException
     * @return array{0: ArrayObject, 1: ArrayObject}
     */
    protected function getFormSpecificationInternal($entity): array
    {
        $formSpec = new ArrayObject();
        $filterSpec = new ArrayObject();

        $reflection = new ReflectionClass($entity);
        $annotations = new AnnotationCollection();
        foreach ($reflection->getAttributes() as $attribute) {
            $annotations[] = $attribute->newInstance();
        }

        $this->configureForm($annotations, $reflection, $formSpec, $filterSpec);

        foreach ($reflection->getProperties() as $property) {
            $annotations = new AnnotationCollection();
            foreach ($property->getAttributes() as $attribute) {
                $annotations[] = $attribute->newInstance();
            }

            $this->configureElement($annotations, $property, $formSpec, $filterSpec);
        }

        return [$formSpec, $filterSpec];
    }
}
