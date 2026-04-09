<?php

declare(strict_types=1);

namespace DoctrineModule\Service;

use Laminas\EventManager\EventManagerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * CLI Application ServiceManager factory responsible for instantiating a Symfony CLI application
 */
final class CliFactory implements FactoryInterface
{
    protected EventManagerInterface|null $events = null;

    protected HelperSet $helperSet;

    protected array $commands = [];

    public function getEventManager(ContainerInterface $container): EventManagerInterface
    {
        if (!$this->events instanceof \Laminas\EventManager\EventManagerInterface) {
            $events = $container->get('EventManager');

            $events->addIdentifiers([self::class, 'doctrine']);

            $this->events = $events;
        }

        return $this->events;
    }

    /**
     * {@inheritDoc}
     *
     * @return Application
     */
    public function __invoke(ContainerInterface $container, $requestedName, array|null $options = null): Application
    {
        $cli = new Application();
        $cli->setName(name: 'DoctrineModule Command Line Interface');
        $cli->setHelperSet(helperSet: new HelperSet());
        $cli->setCatchExceptions(boolean: true);
        $cli->setAutoExit(boolean: false);

        // Load commands using event
        $this->getEventManager(container: $container)->trigger(eventName: 'loadCli.post', target: $cli, argv: ['ServiceManager' => $container]);

        return $cli;
    }
}
