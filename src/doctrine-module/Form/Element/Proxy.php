<?php

declare(strict_types=1);

namespace DoctrineModule\Form\Element;

use BadMethodCallException;
use Doctrine\Common\Collections\Collection;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Persistence\ObjectManager;
use DoctrineModule\Form\Element\Exception\InvalidRepositoryResultException;
use DoctrineModule\Persistence\ObjectManagerAwareInterface;
use Jield\Stdlib\Value;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\Guard\ArrayOrTraversableGuardTrait;
use ReflectionMethod;
use RuntimeException;
use Traversable;
use Webmozart\Assert\Assert;

use function array_change_key_case;
use function array_key_exists;
use function array_shift;
use function call_user_func;
use function count;
use function current;
use function gettype;
use function is_callable;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;
use function strtolower;
use function trim;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
// @mago-expect lint:too-many-properties(1)
class Proxy implements ObjectManagerAwareInterface
{
    use ArrayOrTraversableGuardTrait;

    /** @var iterable<object>|null */
    protected ?iterable $objects = null;

    protected ?string $targetClass = null;

    protected array $valueOptions = [];

    protected array $findMethod = [];

    protected mixed $property = null;

    protected array $optionAttributes = [];

    /** @var callable $labelGenerator A callable used to create a label based on an item in the collection an Entity */
    protected $labelGenerator;

    protected ?bool $isMethod = null;

    protected ?ObjectManager $objectManager = null;

    protected bool $displayEmptyItem = false;

    protected string $emptyItemLabel = '';

    protected ?string $optgroupIdentifier = null;

    protected ?string $optgroupDefault = null;

    protected Inflector $inflector;

    public function __construct(?Inflector $inflector = null)
    {
        $this->inflector = $inflector ?? InflectorFactory::create()->build();
    }

    public function setOptions(iterable $options): void
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray(iterator: $options);
        }

        if (($options['object_manager'] ?? null) !== null) {
            $this->setObjectManager(objectManager: Assert::isInstanceOf(
                $options['object_manager'],
                \Doctrine\Persistence\ObjectManager::class,
            ));
        }

        if (($options['target_class'] ?? null) !== null) {
            $this->setTargetClass(targetClass: Assert::string($options['target_class']));
        }

        if (($options['property'] ?? null) !== null) {
            $this->setProperty(property: Assert::string($options['property']));
        }

        if (($options['label_generator'] ?? null) !== null) {
            $this->setLabelGenerator(callable: Assert::isCallable($options['label_generator']));
        }

        if (($options['find_method'] ?? null) !== null) {
            $this->setFindMethod(findMethod: Assert::isArray($options['find_method']));
        }

        if (($options['is_method'] ?? null) !== null) {
            $this->setIsMethod(method: Assert::boolean($options['is_method']));
        }

        if (($options['display_empty_item'] ?? null) !== null) {
            $this->setDisplayEmptyItem(displayEmptyItem: Assert::boolean($options['display_empty_item']));
        }

        if (($options['empty_item_label'] ?? null) !== null) {
            $this->setEmptyItemLabel(emptyItemLabel: Assert::string($options['empty_item_label']));
        }

        if (($options['option_attributes'] ?? null) !== null) {
            $this->setOptionAttributes(optionAttributes: Assert::isArray($options['option_attributes']));
        }

        if (($options['optgroup_identifier'] ?? null) !== null) {
            $this->setOptgroupIdentifier(optgroupIdentifier: Assert::string($options['optgroup_identifier']));
        }

        if (($options['optgroup_default'] ?? null) === null) {
            return;
        }

        $this->setOptgroupDefault(optgroupDefault: Assert::string($options['optgroup_default']));
    }

    /**
     * @throws \Exception
     */
    public function getValueOptions(): mixed
    {
        if ($this->valueOptions === []) {
            $this->loadValueOptions();
        }

        return $this->valueOptions;
    }

    /**
     * @throws \Exception
     */
    public function getObjects(): mixed
    {
        $this->loadObjects();

        return $this->objects;
    }

    /**
     * Set the label for the empty option
     */
    public function setEmptyItemLabel(string $emptyItemLabel): static
    {
        $this->emptyItemLabel = $emptyItemLabel;

        return $this;
    }

    public function getEmptyItemLabel(): string
    {
        return $this->emptyItemLabel;
    }

    public function getOptionAttributes(): array
    {
        return $this->optionAttributes;
    }

    public function setOptionAttributes(array $optionAttributes): void
    {
        $this->optionAttributes = $optionAttributes;
    }

    /**
     * Set a flag, whether to include the empty option at the beginning or not
     */
    public function setDisplayEmptyItem(bool $displayEmptyItem): static
    {
        $this->displayEmptyItem = $displayEmptyItem;

        return $this;
    }

    public function getDisplayEmptyItem(): bool
    {
        return $this->displayEmptyItem;
    }

    /**
     * Set the object manager
     */
    public function setObjectManager(ObjectManager $objectManager): void
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Get the object manager
     *
     * @throws RuntimeException
     */
    public function getObjectManager(): ObjectManager
    {
        if (!$this->objectManager instanceof \Doctrine\Persistence\ObjectManager) {
            throw new RuntimeException(message: 'No object manager was set');
        }

        return $this->objectManager;
    }

    /**
     * Set the FQCN of the target object
     */
    public function setTargetClass(string $targetClass): static
    {
        $this->targetClass = $targetClass;

        return $this;
    }

    /**
     * Get the target class
     *
     * @return class-string
     * @throws RuntimeException
     */
    public function getTargetClass(): string
    {
        if (!$this->targetClass) {
            throw new RuntimeException(message: 'No target class was set');
        }

        return Value::classString($this->targetClass);
    }

    /**
     * Set the property to use as the label in the options
     */
    public function setProperty(string $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getProperty(): mixed
    {
        return $this->property;
    }

    /**
     * Set the label generator callable that is responsible for generating labels for the items in the collection
     *
     * @param callable $callable A callable used to create a label based off of an Entity
     */
    public function setLabelGenerator(callable $callable): void
    {
        $this->labelGenerator = $callable;
    }

    public function getLabelGenerator(): ?callable
    {
        return $this->labelGenerator;
    }

    public function getOptgroupIdentifier(): ?string
    {
        return $this->optgroupIdentifier;
    }

    public function setOptgroupIdentifier(string $optgroupIdentifier): void
    {
        $this->optgroupIdentifier = $optgroupIdentifier;
    }

    public function getOptgroupDefault(): ?string
    {
        return $this->optgroupDefault;
    }

    public function setOptgroupDefault(string $optgroupDefault): void
    {
        $this->optgroupDefault = $optgroupDefault;
    }

    /**
     * Set if the property is a method to use as the label in the options
     */
    public function setIsMethod(bool $method): static
    {
        $this->isMethod = $method;

        return $this;
    }

    public function getIsMethod(): ?bool
    {
        return $this->isMethod;
    }

    /** Set the findMethod property to specify the method to use on repository
     */
    public function setFindMethod(array $findMethod): static
    {
        $this->findMethod = $findMethod;

        return $this;
    }

    /**
     * Get findMethod definition
     */
    public function getFindMethod(): array
    {
        return $this->findMethod;
    }

    protected function generateLabel(mixed $targetEntity): ?string
    {
        if ($this->getLabelGenerator() === null) {
            return null;
        }

        return Value::nullableString(call_user_func(Assert::isCallable($this->getLabelGenerator()), $targetEntity));
    }

    /**
     * @throws \RuntimeException
     * @throws BadMethodCallException
     */
    public function getValue(mixed $value): mixed
    {
        $metadata = $this->getObjectManager()->getClassMetadata(className: $this->getTargetClass());

        if (is_object(value: $value)) {
            // These alternatives are mutually exclusive; the following code consumes the selected state.
            // @mago-expect lint:no-else-clause(1)
            if ($value instanceof Collection) {
                $data = [];

                /** @mago-var mixed $object */
                foreach ($value as $object) {
                    $values = $metadata->getIdentifierValues(Assert::object($object));
                    $data[] = array_shift(array: $values);
                }

                $value = $data;
            } else {
                $metadata = $this->getObjectManager()->getClassMetadata(className: $value::class);
                $identifier = $metadata->getIdentifierFieldNames();

                if (count(value: $identifier) > 1) {
                    // Handling composite (multiple) identifiers is not yet supported
                    throw new BadMethodCallException(message: sprintf(
                        'Composite identiers are not yet supported in %s.',
                        self::class,
                    ));
                }

                /** @mago-var mixed $value */
                $value = current(array: $metadata->getIdentifierValues($value));
            }
        }

        return $value;
    }

    /**
     * Load objects
     *
     * @throws \Exception
     */
    protected function loadObjects(): void
    {
        if ($this->objects) {
            return;
        }

        $findMethod = $this->getFindMethod();

        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(3)
        if ($findMethod === []) {
            $findMethodName = 'findAll';
            $repository = Assert::notNull($this->objectManager)->getRepository(className: $this->getTargetClass());
            $objects = $repository->findAll();
        } else {
            if (($findMethod['name'] ?? null) === null) {
                throw new RuntimeException(message: 'No method name was set');
            }

            /** @mago-var mixed $findMethodName */
            $findMethodName = $findMethod['name'];
            $findMethodParams = ($findMethod['params'] ?? null) !== null
                ? array_change_key_case(array: Value::stringKeyedArray($findMethod['params']))
                : [];
            $repository = Assert::notNull($this->objectManager)->getRepository(className: $this->getTargetClass());

            if (!method_exists(object_or_class: $repository, method: Assert::string($findMethodName))) {
                throw new RuntimeException(message: sprintf(
                    'Method "%s" could not be found in repository "%s"',
                    Value::scalar($findMethodName),
                    $repository::class,
                ));
            }

            $r = new ReflectionMethod(objectOrMethod: $repository, method: Assert::nullOrString($findMethodName));
            $args = [];

            foreach ($r->getParameters() as $param) {
                if (array_key_exists(key: strtolower(string: $param->getName()), array: $findMethodParams)) {
                    $args[] = $findMethodParams[strtolower(string: $param->getName())];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif (!$param->isOptional()) {
                    throw new RuntimeException(message: sprintf(
                        'Required parameter "%s" with no default value for method "%s" in repository "%s"'
                        . ' was not provided',
                        $param->getName(),
                        Value::scalar($findMethodName),
                        $repository::class,
                    ));
                }
            }

            $objects = $r->invokeArgs(object: $repository, args: $args);
        }

        $this->guardForArrayOrTraversable(
            data: $objects,
            dataName: sprintf('%s::%s() return value', $repository::class, Value::scalar($findMethodName)),
            exceptionClass: InvalidRepositoryResultException::class,
        );

        $this->objects = array_map(
            Assert::object(...),
            is_array($objects) ? $objects : iterator_to_array(Assert::isIterable($objects)),
        );
    }

    /**
     * Load value options
     *
     * @throws \Exception
     */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    protected function loadValueOptions(): void
    {
        $metadata = $this->getObjectManager()->getClassMetadata(className: $this->getTargetClass());
        $identifier = $metadata->getIdentifierFieldNames();
        /** @mago-var mixed $objects */
        $objects = $this->getObjects();
        $options = [];
        $optionAttributes = [];

        if ($this->displayEmptyItem) {
            $options[''] = $this->getEmptyItemLabel();
        }

        /**
         * @mago-var mixed $key
         * @mago-var mixed $object
         */
        foreach (Assert::isIterable($objects) as $key => $object) {
            $generatedLabel = $this->generateLabel(targetEntity: $object);
            // These alternatives are mutually exclusive; the following code consumes the selected state.
            // @mago-expect lint:no-else-clause(2)
            if ($generatedLabel !== null) {
                $label = $generatedLabel;
            } elseif ($this->property !== null) {
                /** @mago-var mixed $property */
                $property = $this->property;
                if (
                    (
                        $this->getIsMethod() === false
                        || $this->getIsMethod() === null
                    )
                    && !$metadata->hasField(fieldName: Assert::string($property))
                ) {
                    throw new RuntimeException(message: sprintf(
                        'Property "%s" could not be found in object "%s"',
                        Value::scalar($property),
                        $this->getTargetClass(),
                    ));
                }

                $getter = 'get' . $this->inflector->classify(word: Assert::string($property));

                if (!is_callable(value: [$object, $getter])) {
                    throw new RuntimeException(message: sprintf(
                        'Method "%s::%s" is not callable',
                        $this->targetClass,
                        $getter,
                    ));
                }

                /** @mago-var mixed $label */
                $label = Value::call($object, $getter);
            } else {
                if (!is_callable(value: [$object, '__toString'])) {
                    throw new RuntimeException(message: sprintf(
                        '%s must have a "__toString()" method defined if you have not set a property'
                        . ' or method to use.',
                        $this->getTargetClass(),
                    ));
                }

                $label = (string) $object;
            }

            /** @mago-var mixed $value */
            $value = count(value: $identifier) > 1
                ? $key
                : current(array: $metadata->getIdentifierValues(Assert::object($object)));

            /** @mago-var mixed $optionValue */
            foreach ($this->getOptionAttributes() as $optionKey => $optionValue) {
                if (is_string(value: $optionValue)) {
                    $optionAttributes[$optionKey] = $optionValue;

                    continue;
                }

                if (is_callable(value: $optionValue)) {
                    /** @mago-var mixed $callableValue */
                    $callableValue = call_user_func($optionValue, $object);
                    $optionAttributes[$optionKey] = (string) $callableValue;

                    continue;
                }

                throw new RuntimeException(message: sprintf(
                    'Parameter "option_attributes" expects an array of key => value where value is of type'
                    . '"string" or "callable". Value of type "%s" found.',
                    gettype(value: $optionValue),
                ));
            }

            // If no optgroup_identifier has been configured, apply default handling and continue
            if ($this->getOptgroupIdentifier() === null) {
                $options[] = ['label' => $label, 'value' => $value, 'attributes' => $optionAttributes];

                continue;
            }

            // optgroup_identifier found, handle grouping
            $optgroupGetter = 'get' . $this->inflector->classify(word: Assert::string($this->getOptgroupIdentifier()));

            if (!is_callable(value: [$object, $optgroupGetter])) {
                throw new RuntimeException(message: sprintf(
                    'Method "%s::%s" is not callable',
                    $this->targetClass,
                    $optgroupGetter,
                ));
            }

            /** @mago-var mixed $optgroup */
            $optgroup = Value::call($object, $optgroupGetter);

            // optgroup_identifier contains a valid group-name. Handle default grouping.
            if ($optgroup !== null && trim(string: Assert::string($optgroup)) !== '') {
                $options[$optgroup]['label'] = $optgroup;
                $options[$optgroup]['options'][] = [
                    'label' => $label,
                    'value' => $value,
                    'attributes' => $optionAttributes,
                ];

                continue;
            }

            $optgroupDefault = $this->getOptgroupDefault();

            // No optgroup_default has been provided. Line up without a group
            if ($optgroupDefault === null) {
                $options[] = ['label' => $label, 'value' => $value, 'attributes' => $optionAttributes];

                continue;
            }

            // Line up entry with optgroup_default
            $options[$optgroupDefault]['label'] = $optgroupDefault;
            $options[$optgroupDefault]['options'][] = [
                'label' => $label,
                'value' => $value,
                'attributes' => $optionAttributes,
            ];
        }

        $this->valueOptions = $options;
    }
}
