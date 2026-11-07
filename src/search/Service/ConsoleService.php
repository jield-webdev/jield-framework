<?php

declare(strict_types=1);

namespace Jield\Search\Service;

use InvalidArgumentException;
use Jield\Search\Entity\HasSearchInterface;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function sprintf;

class ConsoleService
{
    private array $services = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        $services = Value::path($container->get('config'), ['search', 'cores']);

        //Do an init check
        /** @var mixed $key
         * @var mixed $service
         */
        foreach (Value::iterable($services ?? []) as $key => $service) {
            if (!$this->container->has(Value::string(
                Value::accessible($service)['service'],
            ))) {
                throw new InvalidArgumentException(message: sprintf(
                    'Service %s not found',
                    Value::scalar(Value::path($service, ['service'])),
                ));
            }

            Assert::isInstanceOf(
                value: Value::instantiate(Value::accessible($service)['entity']),
                class: HasSearchInterface::class,
            );

            $this->services[Value::key($key)] = $service;
        }
    }

    public function resetIndex(
        OutputInterface $output,
        string $index,
        bool $clearIndex = false,
        bool $shallow = false,
    ): void {
        if ($index === 'all') {
            foreach ($this->services as $service) {
                /** @var AbstractSearchService $serviceInstance */
                $serviceInstance = $this->container->get(Value::string(
                    Value::accessible($service)['service'],
                ));
                /** @var HasSearchInterface $entity */
                $entity = Value::instantiate(Value::accessible($service)['entity']);

                /** @var mixed $limit */
                $limit = $service['limit'] ?? 50;
                /** @var mixed $criteria */
                $criteria = $service['criteria'] ?? [];

                $serviceInstance->updateCollection(
                    output: $output,
                    entity: $entity,
                    clearIndex: $clearIndex,
                    shallow: $shallow,
                    limit: Value::int($limit),
                    criteria: Value::array($criteria),
                );
            }
            return;
        }

        if (!isset($this->services[$index])) {
            $output->writeln(messages: sprintf('<error>Index %s not found</error>', $index));

            return;
        }

        $output->writeln(messages: sprintf('<info>Updating index %s</info>', $index));

        //We have the service, so get it
        /** @var AbstractSearchService $serviceInstance */
        $serviceInstance = $this->container->get(Value::string(
            Value::accessible($this->services[$index])['service'],
        ));
        /** @var HasSearchInterface $entity */
        $entity = Value::instantiate(Value::accessible($this->services[$index])['entity']);

        /** @var mixed $limit */
        $limit = $this->services[$index]['limit'] ?? 50;
        /** @var mixed $criteria */
        $criteria = $this->services[$index]['criteria'] ?? [];

        $serviceInstance->updateCollection(
            output: $output,
            entity: $entity,
            clearIndex: $clearIndex,
            shallow: $shallow,
            limit: Value::int($limit),
            criteria: Value::array($criteria),
        );
    }

    public function syncIndex(OutputInterface $output): void
    {
        foreach ($this->services as $service) {
            if ($service['skip_sync'] ?? false) {
                $output->writeln(messages: '');
                $output->writeln(messages: sprintf(
                    '<comment>Skipping sync for %s</comment>',
                    Value::scalar(Value::accessible($service)['service']),
                ));
                continue;
            }

            /** @var AbstractSearchService $serviceInstance */
            $serviceInstance = $this->container->get(Value::string(
                Value::accessible($service)['service'],
            ));
            /** @var HasSearchInterface $entity */
            $entity = Value::instantiate(Value::accessible($service)['entity']);
            /** @var mixed $criteria */
            $criteria = $service['criteria'] ?? [];

            $serviceInstance->syncIndex(
                output: $output,
                entity: $entity,
                criteria: Value::array($criteria),
            );
        }
    }

    public function testIndex(OutputInterface $output, bool $clearIndex = false): void
    {
        foreach ($this->services as $service) {
            /** @var AbstractSearchService $serviceInstance */
            $serviceInstance = $this->container->get(Value::string(
                Value::accessible($service)['service'],
            ));
            /** @var HasSearchInterface $entity */
            $entity = Value::instantiate(Value::accessible($service)['entity']);

            $serviceInstance->testIndex(output: $output, entity: $entity, clearIndex: $clearIndex);
        }
    }

    public function getCores(): array
    {
        return $this->services;
    }
}
