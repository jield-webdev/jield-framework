<?php

declare(strict_types=1);

namespace DoctrineModule\Validator\Service;

use DoctrineModule\Validator\ObjectExists;
use Psr\Container\ContainerInterface;

final class ObjectExistsFactory extends AbstractValidatorFactory
{
    protected string $validatorClass = ObjectExists::class;

    /**
     * {@inheritDoc}
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ObjectExists
    {
        $repository = $this->getRepository(container: $container, options: $options);

        return new ObjectExists(options: $this->merge(previousOptions: \Jield\Stdlib\Value::array($options), newOptions: [
            'object_repository' => $repository,
            'fields' => $this->getFields(options: \Jield\Stdlib\Value::array($options)),
        ]));
    }
}
