<?php

declare(strict_types=1);

namespace Jield\Search\Service;

use InvalidArgumentException;
use Jield\Search\Entity\HasSearchInterface;
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
        /** @var mixed $services */
        $services = \Jield\Stdlib\Value::path($container->get('config'), ['search', 'cores']);

        //Do an init check
        /** @var mixed $key
         * @var mixed $service
         */
        foreach (\Jield\Stdlib\Value::iterable($services ?? []) as $key => $service) {
            if (!$this->container->has(\Jield\Stdlib\Value::string(\Jield\Stdlib\Value::accessible($service)['service']))) {
                throw new InvalidArgumentException(message: sprintf('Service %s not found', \Jield\Stdlib\Value::scalar(\Jield\Stdlib\Value::path($service, ['service']))));
            }

            Assert::isInstanceOf(value: \Jield\Stdlib\Value::instantiate(\Jield\Stdlib\Value::accessible($service)['entity']), class: HasSearchInterface::class);

            $this->services[\Jield\Stdlib\Value::key($key)] = $service;
        }
    }

    public function resetIndex(
        OutputInterface $output,
        string $index,
        bool $clearIndex = false,
        bool $shallow = false,
    ): void {
        if ($index === 'all') {
            /** @var mixed $service */
            foreach ($this->services as $service) {
                /** @var AbstractSearchService $serviceInstance */
                $serviceInstance = $this->container->get(\Jield\Stdlib\Value::string(\Jield\Stdlib\Value::accessible($service)['service']));
                /** @var HasSearchInterface $entity */
                $entity = \Jield\Stdlib\Value::instantiate(\Jield\Stdlib\Value::accessible($service)['entity']);

                /** @var mixed $limit */
                $limit = $service['limit'] ?? 50;
                /** @var mixed $criteria */
                $criteria = $service['criteria'] ?? [];

                $serviceInstance->updateCollection(
                    output: $output,
                    entity: $entity,
                    clearIndex: $clearIndex,
                    shallow: $shallow,
                    limit: \Jield\Stdlib\Value::int($limit),
                    criteria: \Jield\Stdlib\Value::array($criteria),
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
        $serviceInstance = $this->container->get(\Jield\Stdlib\Value::string(\Jield\Stdlib\Value::accessible($this->services[$index])['service']));
        /** @var HasSearchInterface $entity */
        $entity = \Jield\Stdlib\Value::instantiate(\Jield\Stdlib\Value::accessible($this->services[$index])['entity']);

        /** @var mixed $limit */
        $limit = $this->services[$index]['limit'] ?? 50;
        /** @var mixed $criteria */
        $criteria = $this->services[$index]['criteria'] ?? [];

        $serviceInstance->updateCollection(
            output: $output,
            entity: $entity,
            clearIndex: $clearIndex,
            shallow: $shallow,
            limit: \Jield\Stdlib\Value::int($limit),
            criteria: \Jield\Stdlib\Value::array($criteria),
        );
    }

    public function syncIndex(OutputInterface $output): void
    {
        /** @var mixed $service */
        foreach ($this->services as $service) {
            if ($service['skip_sync'] ?? false) {
                $output->writeln(messages: '');
                $output->writeln(messages: sprintf('<comment>Skipping sync for %s</comment>', \Jield\Stdlib\Value::scalar(\Jield\Stdlib\Value::accessible($service)['service'])));
                continue;
            }

            /** @var AbstractSearchService $serviceInstance */
            $serviceInstance = $this->container->get(\Jield\Stdlib\Value::string(\Jield\Stdlib\Value::accessible($service)['service']));
            /** @var HasSearchInterface $entity */
            $entity = \Jield\Stdlib\Value::instantiate(\Jield\Stdlib\Value::accessible($service)['entity']);
            /** @var mixed $criteria */
            $criteria = $service['criteria'] ?? [];

            $serviceInstance->syncIndex(output: $output, entity: $entity, criteria: \Jield\Stdlib\Value::array($criteria));
        }
    }

    public function testIndex(OutputInterface $output, bool $clearIndex = false): void
    {
        /** @var mixed $service */
        foreach ($this->services as $service) {
            /** @var AbstractSearchService $serviceInstance */
            $serviceInstance = $this->container->get(\Jield\Stdlib\Value::string(\Jield\Stdlib\Value::accessible($service)['service']));
            /** @var HasSearchInterface $entity */
            $entity = \Jield\Stdlib\Value::instantiate(\Jield\Stdlib\Value::accessible($service)['entity']);

            $serviceInstance->testIndex(output: $output, entity: $entity, clearIndex: $clearIndex);
        }
    }

    public function getCores(): array
    {
        return $this->services;
    }
}
