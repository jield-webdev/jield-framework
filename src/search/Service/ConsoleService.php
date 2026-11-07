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
        /** @mago-var mixed $services */
        $services = Value::path($container->get('config'), ['search', 'cores']);

        //Do an init check
        /**
         * @mago-var mixed $key
         * @mago-var mixed $service
         */
        foreach (Assert::isIterable($services ?? []) as $key => $service) {
            $service = Assert::isArray($service);
            if (!$this->container->has(Assert::string(Value::accessible($service)['service']))) {
                throw new InvalidArgumentException(message: sprintf('Service %s not found', Value::scalar(Value::path($service, [
                    'service',
                ]))));
            }

            Assert::isInstanceOf(value: Value::instantiate($service['entity']), class: HasSearchInterface::class);

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
            /** @mago-var mixed $service */
            foreach ($this->services as $service) {
                $service = Assert::isArray($service);
                /** @var AbstractSearchService $serviceInstance */
                $serviceInstance = $this->container->get(Assert::string(Value::accessible($service)['service']));
                /** @var HasSearchInterface $entity */
                $entity = Value::instantiate($service['entity']);

                /** @mago-var mixed $limit */
                $limit = $service['limit'] ?? 50;
                /** @mago-var mixed $criteria */
                $criteria = $service['criteria'] ?? [];

                $serviceInstance->updateCollection(
                    output: $output,
                    entity: $entity,
                    clearIndex: $clearIndex,
                    shallow: $shallow,
                    limit: Assert::integer($limit),
                    criteria: Assert::isArray($criteria),
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
        $serviceInstance = $this->container->get(Assert::string(Value::accessible($this->services[$index])['service']));
        /** @var HasSearchInterface $entity */
        $entity = Value::instantiate(Value::accessible($this->services[$index])['entity']);

        /** @mago-var mixed $limit */
        $limit = $this->services[$index]['limit'] ?? 50;
        /** @mago-var mixed $criteria */
        $criteria = $this->services[$index]['criteria'] ?? [];

        $serviceInstance->updateCollection(
            output: $output,
            entity: $entity,
            clearIndex: $clearIndex,
            shallow: $shallow,
            limit: Assert::integer($limit),
            criteria: Assert::isArray($criteria),
        );
    }

    public function syncIndex(OutputInterface $output): void
    {
        /** @mago-var mixed $service */
        foreach ($this->services as $service) {
                $service = Assert::isArray($service);
            if ($service['skip_sync'] ?? false) {
                $output->writeln(messages: '');
                $output->writeln(messages: sprintf('<comment>Skipping sync for %s</comment>', Value::scalar(Value::accessible($service)['service'])));
                continue;
            }

            /** @var AbstractSearchService $serviceInstance */
            $serviceInstance = $this->container->get(Assert::string(Value::accessible($service)['service']));
            /** @var HasSearchInterface $entity */
            $entity = Value::instantiate($service['entity']);
            /** @mago-var mixed $criteria */
            $criteria = $service['criteria'] ?? [];

            $serviceInstance->syncIndex(output: $output, entity: $entity, criteria: Assert::isArray($criteria));
        }
    }

    public function testIndex(OutputInterface $output, bool $clearIndex = false): void
    {
        /** @mago-var mixed $service */
        foreach ($this->services as $service) {
                $service = Assert::isArray($service);
            /** @var AbstractSearchService $serviceInstance */
            $serviceInstance = $this->container->get(Assert::string(Value::accessible($service)['service']));
            /** @var HasSearchInterface $entity */
            $entity = Value::instantiate($service['entity']);

            $serviceInstance->testIndex(output: $output, entity: $entity, clearIndex: $clearIndex);
        }
    }

    public function getCores(): array
    {
        return $this->services;
    }
}
