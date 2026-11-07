<?php

declare(strict_types=1);

namespace DoctrineModule\Validator\Service;

use DoctrineModule\Validator\UniqueObject;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

final class UniqueObjectFactory extends AbstractValidatorFactory
{
    protected string $validatorClass = UniqueObject::class;

    /**
     * {@inheritDoc}
     * @throws Exception\ServiceCreationException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UniqueObject
    {
        $useContext = ($options['use_context'] ?? null) !== null ? Value::truthy($options['use_context']) : false;

        return new UniqueObject(options: $this->merge(previousOptions: Assert::isArray($options), newOptions: [
            'object_manager' => $this->getObjectManager(container: $container, options: $options),
            'use_context' => $useContext,
            'object_repository' => $this->getRepository(container: $container, options: $options),
            'fields' => $this->getFields(options: Assert::isArray($options)),
        ]));
    }
}
