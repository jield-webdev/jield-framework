<?php

declare(strict_types=1);

namespace DoctrineModule\Validator\Service;

use DoctrineModule\Validator\NoObjectExists;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

final class NoObjectExistsFactory extends AbstractValidatorFactory
{
    protected string $validatorClass = NoObjectExists::class;

    /**
     * {@inheritDoc}
     * @throws Exception\ServiceCreationException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): NoObjectExists
    {
        $repository = $this->getRepository(container: $container, options: $options);

        return new NoObjectExists(options: $this->merge(previousOptions: Assert::isArray($options), newOptions: [
            'object_repository' => $repository,
            'fields' => $this->getFields(options: Assert::isArray($options)),
        ]));
    }
}
