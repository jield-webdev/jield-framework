<?php

declare(strict_types=1);

namespace Jield\Form\Annotation;

use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventInterface;
use ReflectionClass;

use function assert;

/**
 * Base annotations listener.
 *
 * Provides an implementation of detach() that should work with any listener.
 * Also provides listeners for the "Name" annotation -- handleNameAnnotation()
 * will listen for the "Name" annotation, while discoverFallbackName() listens
 * on the "discoverName" event and will use the class or property name, as
 * discovered via reflection, if no other annotation has provided the name
 * already.
 */
abstract class AbstractAnnotationsListener extends AbstractListenerAggregate
{
    /**
     * Attempt to discover a name set via annotation
     *
     * @return false|string
     */
    public function handleNameAnnotation(EventInterface $e)
    {
        /** @mago-var mixed $annotations */
        $annotations = $e->getParam('annotations');
        assert($annotations instanceof AnnotationCollection);

        if (!$annotations->hasAnnotation(Name::class)) {
            return false;
        }

        foreach ($annotations as $annotation) {
            if (!$annotation instanceof Name) {
                continue;
            }
            return $annotation->getName();
        }

        return false;
    }

    /**
     * Discover the fallback name via reflection
     */
    public function discoverFallbackName(EventInterface $e): string
    {
        /** @mago-var mixed $reflection */
        $reflection = $e->getParam('reflection');
        if ($reflection instanceof ReflectionClass) {
            return $reflection->getShortName();
        }

        return \Webmozart\Assert\Assert::string(Value::call($reflection, 'getName'));
    }
}
