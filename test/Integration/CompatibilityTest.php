<?php

declare(strict_types=1);

namespace JieldTest\Integration;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use DoctrineModule\Paginator\Adapter\Selectable;
use DoctrineModule\Service\Authentication\AdapterFactory;
use DoctrineModule\Validator\ObjectExists;
use Jield\Authorize\Config as AuthorizeConfig;
use Jield\Authorize\Factory\ConfigResourceProviderServiceFactory;
use Jield\Authorize\Factory\ConfigRuleProviderServiceFactory;
use Jield\Authorize\Provider\Resource\Config as ResourceConfig;
use Jield\Authorize\Provider\Rule\Config as RuleConfig;
use Jield\Cors\Options\CorsOptions;
use Jield\Cors\Service\CorsService;
use Jield\ErrorHandler\HeroTrait;
use Jield\Log\Handler\DoctrineDbalHandler;
use Jield\Mvc\Controller\AbstractRestfulController;
use Jield\Mvc\Router\RouteMatch;
use Laminas\Http\Request;
use Laminas\ServiceManager\ServiceManager;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class CompatibilityTest extends TestCase
{
    public function testResourceAndRuleFactoriesAcceptConfiguredValues(): void
    {
        $resources = ['document', 'parent' => ['child']];
        $rules = ['allow' => [['reader', 'document', 'view']]];
        $container = new ServiceManager([
            'services' => [
                AuthorizeConfig::class => [
                    'resource_providers' => [ResourceConfig::class => $resources],
                    'rule_providers' => [RuleConfig::class => $rules],
                ],
            ],
        ]);

        self::assertSame(
            $resources,
            new ConfigResourceProviderServiceFactory()($container, ResourceConfig::class)->getResources(),
        );
        self::assertSame($rules, new ConfigRuleProviderServiceFactory()($container, RuleConfig::class)->getRules());
    }

    public function testCorsPreflightUsesRouteOptionsAndStringHeaders(): void
    {
        $request = new Request();
        $request->setUri('https://api.example.test/');
        $request->getHeaders()->addHeaderLine('Origin', 'https://app.example.test');
        $service = new CorsService(new CorsOptions(['allowed_origins' => ['*']]));
        $response = $service->createPreflightCorsResponseWithRouteOptions($request, new RouteMatch([
            'cors' => ['max_age' => 600, 'allowed_methods' => ['GET', 'POST']],
        ]));

        self::assertSame('600', $response->getHeaders()->get('Access-Control-Max-Age')->getFieldValue());
        self::assertSame('0', $response->getHeaders()->get('Content-Length')->getFieldValue());
        self::assertSame('GET, POST', $response->getHeaders()->get('Access-Control-Allow-Methods')->getFieldValue());
    }

    public function testDoctrineAuthenticationFactoryUsesItsNamedConfiguration(): void
    {
        $repository = $this->createStub(ObjectRepository::class);
        $manager = $this->createStub(ObjectManager::class);
        $manager->method('getRepository')->willReturn($repository);
        $container = new ServiceManager([
            'services' => [
                'manager' => $manager,
                'config' => [
                    'doctrine' => [
                        'authentication' => ['default' => [
                            'object_manager' => 'manager',
                            'identity_class' => \stdClass::class,
                            'identity_property' => 'email',
                            'credential_property' => 'password',
                        ]],
                    ],
                ],
            ],
        ]);
        $adapter = new AdapterFactory('default')($container, 'adapter');

        self::assertSame('email', $adapter->getOptions()->getIdentityProperty());
        self::assertSame('password', $adapter->getOptions()->getCredentialProperty());
        self::assertSame($manager, $adapter->getOptions()->getObjectManager());
    }

    public function testSelectableCountsTheWholeCollectionAfterPaging(): void
    {
        $adapter = new Selectable(new ArrayCollection(['one', 'two', 'three']));
        self::assertSame([1 => 'two'], $adapter->getItems(1, 1));
        self::assertSame(3, $adapter->count());
    }

    public function testRepositoryValidationReportsIncorrectFieldCounts(): void
    {
        $repository = $this->createStub(ObjectRepository::class);
        $validator = new ObjectExists(['object_repository' => $repository, 'fields' => ['name', 'email']]);

        $this->expectException(\Laminas\Validator\Exception\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('expected number of fields');
        $validator->isValid(['only-one-value']);
    }

    public function testDatabaseLoggingAcceptsCaseInsensitiveLevelsAndWritesRecords(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE application_log (channel TEXT, level INTEGER, level_name TEXT, message TEXT, context TEXT, extra TEXT, created_at TEXT)',
        );
        $handler = new DoctrineDbalHandler($connection, level: 'wArNiNg');
        self::assertSame(Level::Warning, $handler->getLevel());

        $logger = new Logger('test', [$handler]);
        $logger->info('ignored');
        $logger->error('saved', ['id' => 42]);
        $rows = $connection->fetchAllAssociative('SELECT message, level, context FROM application_log');
        self::assertCount(1, $rows);
        self::assertSame('saved', $rows[0]['message']);
        self::assertSame(400, $rows[0]['level']);
        self::assertSame(['id' => 42], json_decode($rows[0]['context'], true, flags: JSON_THROW_ON_ERROR));
    }

    public function testRestBodyParsingPreservesRawBodiesAndDecodesFormFields(): void
    {
        $controller = new class extends AbstractRestfulController {
            public function decode(string $content): mixed
            {
                $request = new Request();
                $request->setContent($content);
                return $this->processBodyContent($request);
            }
        };

        self::assertSame('', $controller->decode(''));
        self::assertSame('&', $controller->decode('&'));
        self::assertSame('raw-body', $controller->decode('raw-body'));
        self::assertSame(
            ['name' => 'Alice', 'tags' => ['one', 'two']],
            $controller->decode('name=Alice&tags[]=one&tags[]=two'),
        );
    }

    public function testErrorHandlerTraitLoadsAndHonorsExcludedPhpErrors(): void
    {
        self::assertTrue(class_exists(\Jield\ErrorHandler\Listener\Mvc::class));
        self::assertTrue(class_exists(\Jield\ErrorHandler\Command\BaseLoggingCommand::class));
        $handler = new class {
            use HeroTrait;

            protected function capturesErrorOutput(): bool
            {
                return true;
            }

            protected function getErrorHandlerConfiguration(): array
            {
                return ['display-settings' => ['exclude-php-errors' => [[E_USER_WARNING, 'ignored']]]];
            }

            protected function handleShutdownException(\ErrorException $exception): void {}
        };

        $previousReporting = error_reporting(E_ALL);
        try {
            $handler->phpErrorHandler(E_USER_WARNING, 'ignored', __FILE__, __LINE__);
            $this->expectException(\ErrorException::class);
            $handler->phpErrorHandler(E_USER_WARNING, 'reported', __FILE__, __LINE__);
        } finally {
            error_reporting($previousReporting);
        }
    }
}
