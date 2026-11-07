<?php

declare(strict_types=1);

namespace JieldTest\Integration;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use DoctrineModule\Form\Element\Proxy;
use Jield\ApiTools\Enum\RouteMethodEnum;
use Jield\ApiTools\ValueObject\ListenerValueObject;
use Jield\AssetManager\Cache\FilePathCache;
use Jield\AssetManager\Exception\RuntimeException;
use Jield\Mvc\Navigation\View\Navigation\Menu;
use Jield\Stdlib\Value;
use Laminas\Permissions\Acl\Role\GenericRole;
use Laminas\View\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use stdClass;

use function Jield\ErrorHandler\detectMessageContentType;

final class LintCompatibilityTest extends TestCase
{
    public function testDynamicValuesKeepTheirFalsySemantics(): void
    {
        foreach ([null, false, 0, 0.0, '', '0', [], new SimpleXMLElement('<root/>')] as $value) {
            self::assertFalse(Value::truthy($value));
        }

        foreach ([true, -1, 0.1, '00', [0], new stdClass()] as $value) {
            self::assertTrue(Value::truthy($value));
        }
    }

    public function testRoutePrivilegesPreserveOverridesAndMethodDefaults(): void
    {
        foreach ([RouteMethodEnum::GET, RouteMethodEnum::POST] as $method) {
            foreach ([null, '', '0', 'manage'] as $privilege) {
                $configuration = new ListenerValueObject(
                    listener: 'example',
                    route: '/example',
                    routeMethod: $method,
                    privilege: $privilege,
                )->toArray();

                $expected = $privilege === 'manage' ? 'manage' : ($method === RouteMethodEnum::GET ? 'view' : 'edit');
                self::assertSame(
                    $expected,
                    $configuration['router']['routes']['example']['options']['defaults']['privilege'],
                );
            }
        }
    }

    public function testNavigationAcceptsSupportedRoleRepresentations(): void
    {
        $menu = new Menu();
        foreach ([null, 'reader', new GenericRole('editor')] as $role) {
            self::assertSame($menu, $menu->setRole($role));
            self::assertSame($role, $menu->getRole());
        }
    }

    public function testDoctrineSelectorLoadsAndReusesItsObjects(): void
    {
        $entity = new stdClass();
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn([$entity]);
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())->method('getRepository')->with(stdClass::class)->willReturn($repository);
        $proxy = new Proxy();
        $proxy->setObjectManager($manager);
        $proxy->setTargetClass(stdClass::class);

        self::assertSame([$entity], $proxy->getObjects());
        self::assertSame([$entity], $proxy->getObjects());
    }

    public function testNavigationRejectsUnrelatedRoleObjects(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Menu()->setRole(new stdClass());
    }

    public function testCacheWriteFailuresRestoreThePreviousErrorHandler(): void
    {
        $directory = sys_get_temp_dir() . '/jield-cache-' . bin2hex(random_bytes(8));
        $temporaryPath = $directory . '/AssetManagerFilePathCache_asset.txt';
        mkdir($temporaryPath, 0700, true);
        $handler = static fn (): bool => true;
        set_error_handler($handler);

        try {
            $cache = new FilePathCache($directory, 'asset.txt');
            try {
                $cache->set('asset', 'contents');
                self::fail('Writing a file over a directory must fail.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to write file', $exception->getMessage());
            }

            $previous = set_error_handler(static fn (): bool => false);
            restore_error_handler();
            self::assertSame($handler, $previous);
        } finally {
            restore_error_handler();
            rmdir($temporaryPath);
            rmdir($directory);
        }
    }

    public function testErrorMessageFormatsRemainDistinct(): void
    {
        self::assertSame('text/plain', detectMessageContentType('plain text'));
        self::assertSame('text/html', detectMessageContentType('<p>html</p>'));
        self::assertSame('application/problem+json', detectMessageContentType('{"error":"invalid"}'));
    }
}
