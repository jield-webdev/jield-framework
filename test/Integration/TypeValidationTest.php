<?php

declare(strict_types=1);

namespace JieldTest\Integration;

use Doctrine\DBAL\Connection;
use DoctrineORMModule\Options\DBALConnection;
use Jield\ModuleManager\Listener\LocatorRegistrationListener;
use Jield\ModuleManager\ModuleEvent;
use Jield\Mvc\Application;
use Jield\Mvc\Factory\ViewTemplatePathStackFactory;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventManager;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webmozart\Assert\InvalidArgumentException;

final class TypeValidationTest extends TestCase
{
    public function testBootstrapRegistersModuleClassNamesAndPreservesExistingServices(): void
    {
        $module = new stdClass();
        $existing = new stdClass();

        foreach ([null, $existing] as $service) {
            $services = new ServiceManager();
            if ($service !== null) {
                $services->setService(stdClass::class, $service);
            }
            $application = new Application($services, new EventManager(), new Request(), new Response());
            $listener = new LocatorRegistrationListener();
            $listener->onLoadModule(new ModuleEvent()->setModule($module));

            $listener->onBootstrap(new MvcEvent()->setApplication($application));

            self::assertSame($service ?? $module, $services->get(stdClass::class));
        }
    }

    public function testConfiguredConnectionWrapperCanBeTheBaseClass(): void
    {
        $options = new DBALConnection();
        $options->setWrapperClass(Connection::class);
        $options->setDriverClass(null);

        self::assertSame(Connection::class, $options->getWrapperClass());
        self::assertNull($options->getDriverClass());
    }

    public function testConfiguredConnectionWrapperRejectsUnrelatedClasses(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DBALConnection()->setWrapperClass(stdClass::class);
    }

    public function testTemplatePathConfigurationRejectsEmptyPaths(): void
    {
        $services = new ServiceManager([
            'services' => ['config' => ['view_manager' => ['template_path_stack' => ['']]]],
        ]);
        $this->expectException(InvalidArgumentException::class);

        new ViewTemplatePathStackFactory()($services, 'ViewTemplatePathStack');
    }
}
