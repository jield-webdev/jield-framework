<?php

declare(strict_types=1);

namespace Jield\Form\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Webmozart\Assert\Assert;

use function is_array;
use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Hydrator annotation
 *
 * Use this annotation to specify a specific hydrator class to use with the form.
 * The value should be a string indicating the fully qualified class name of the
 * hydrator to use.
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute]
final class Hydrator
{
    /** @var string */
    private $type;

    /** @var array */
    private $options;

    /**
     * Receive and process the contents of an annotation
     *
     * @param string|array $type
     */
    public function __construct($type, array $options = [])
    {
        if (is_array($type)) {
            // support for legacy notation with array as first parameter
            trigger_error(
                sprintf(
                    'Passing a single array to the constructor of %s is deprecated since 3.0.0,'
                    . ' please use separate parameters.',
                    self::class,
                ),
                E_USER_DEPRECATED,
            );
            $this->type = Assert::string($type['type'] ?? null);
            $this->options = Assert::isArray($type['options'] ?? $options);
            return;
        }
        $this->type = $type;
        $this->options = $options;
    }

    /**
     * Retrieve the hydrator specification
     */
    public function getHydratorSpecification(): array
    {
        $inputSpec = ['type' => $this->type];
        if (!!$this->options) {
            $inputSpec['options'] = $this->options;
        }

        return $inputSpec;
    }
}
