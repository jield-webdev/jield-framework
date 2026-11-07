<?php

declare(strict_types=1);

namespace DoctrineModule\Validator\Service;

use DoctrineModule\Validator\NoObjectExists;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

final class NoObjectExistsFactory extends AbstractValidatorFactory
{
    protected string $validatorClass = NoObjectExists::class;

    /**
     * {@inheritDoc}
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): NoObjectExists
    {
        $repository = $this->getRepository(container: $container, options: $options);

        return new NoObjectExists(options: $this->merge(
            previousOptions: Value::array($options),
            newOptions: [
                'object_repository' => $repository,
                'fields' => $this->getFields(options: Value::array($options)),
            ],
        ));
    }
}
