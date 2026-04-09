<?php

declare(strict_types=1);

namespace Jield\ApiTools;

use Jield\ApiTools\Enum\RouteMethodEnum;
use Jield\ApiTools\Enum\RouteTypeEnum;
use Jield\ApiTools\Listener\AbstractRoutedListener;
use Jield\ApiTools\ValueObject\ListenerValueObject;
use Jield\ConfigAggregator\GlobTrait;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class ListenerConfigProvider
{
    use GlobTrait;

    private array $listeners = [];

    public function __construct()
    {
        $finder = new Finder();
        $finder->files()->name(patterns: '*Listener.php')->in(dirs: __DIR__ . '/../../../../module/api/src/V1/Rest');

        foreach ($finder as $fileInfo) {
            $className       = $this->getClassNameFromFileInfo(fileInfo: $fileInfo);
            $reflectionClass = $this->getReflectionClassFromClassName(className: $className);

            if ($reflectionClass->isInstantiable() && $reflectionClass->isSubclassOf(
                    class: AbstractRoutedListener::class
                )) {
                //Create a list of classes defined in the main method to see if we have a fetch, create etc defined in the listener
                $definedClasses = [];
                foreach ($reflectionClass->getMethods() as $method) {
                    if ($method->getDeclaringClass()->getName() === $className && !$method->isConstructor()) {
                        $definedClasses[] = $method->getName();
                    }
                }

                //Set the default type
                $routeType   = RouteTypeEnum::ENTITY;
                $routeMethod = RouteMethodEnum::GET;

                /** @var class-string<AbstractRoutedListener> $staticClass */
                $staticClass = $className;

                //Based on the available methods we can determine the routeType and the Method
                //$hasFetch    = $reflectionClass->hasMethod(name: 'fetch');
                //$hasDelete = $reflectionClass->hasMethod(name: 'delete');

                {
                    if (in_array(needle: 'fetchAll', haystack: $definedClasses, strict: true)) {
                        $routeType = RouteTypeEnum::COLLECTION;
                    }
                }
                if (in_array(needle: 'create', haystack: $definedClasses, strict: true)) {
                    //Create can be for entities or collections depending on we have an :id in the route
                    $routeType   = str_contains(
                        haystack: $staticClass::getRoute(),
                        needle: ':'
                    ) ? RouteTypeEnum::ENTITY : RouteTypeEnum::COLLECTION;
                    $routeMethod = RouteMethodEnum::POST;
                }

                if (in_array(needle: 'patch', haystack: $definedClasses, strict: true)) {
                    $routeMethod = RouteMethodEnum::PATCH;
                }

                if (in_array(needle: 'update', haystack: $definedClasses, strict: true)) {
                    $routeType   = RouteTypeEnum::COLLECTION;
                    $routeMethod = RouteMethodEnum::POST;
                }

                if (in_array(needle: 'delete', haystack: $definedClasses, strict: true)) {
                    $routeMethod = RouteMethodEnum::DELETE;
                }


                $this->registerListener(
                    listenerValueObject: new ListenerValueObject(
                        listener: $className,
                        route: $staticClass::getRoute(),
                        routeType: $routeType,
                        routeMethod: $routeMethod,
                        configAbstractFactories: $this->getConstructorArguments(className: $className),
                        entityCollectionWhiteList: $staticClass::getEntityCollectionWhiteList(),
                        pageSize: $staticClass::getPageSize(),
                        maxPageSize: $staticClass::getMaxPageSize(),
                        inputFilterSpecification: $staticClass::getInputFilterSpecification(),
                        routeAssertionClass: $staticClass::getRouteAssertionClass(),
                        privilege: $staticClass::getPrivilege()
                    )
                );
            }
        }
    }

    private function getClassNameFromFileInfo(SplFileInfo $fileInfo): string
    {
        return 'Api\\V1\\Rest\\' . str_replace(
                search: ['/', '.php'],
                replace: ['\\', ''],
                subject: $fileInfo->getRelativePathname()
            );
    }

    private function getReflectionClassFromClassName(string $className): ReflectionClass
    {
        return new ReflectionClass(objectOrClass: $className);
    }

    public function registerListener(ListenerValueObject $listenerValueObject): void
    {
        $this->listeners[] = $listenerValueObject;
    }

    private function getConstructorArguments(string $className): array
    {
        $constructor = new ReflectionMethod(objectOrMethod: $className, method: '__construct');

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType) {
                $arguments[] = $type->getName();
                continue;
            }

            if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
                $arguments[] = (string) $type;
                continue;
            }

            $arguments[] = null;
        }

        return $arguments;
    }

    public function __invoke(): array
    {
        //Return a merged array of all the listeners (with the toArray method called an all objects)
        $configuration = [];

        foreach ($this->listeners as $listener) {
            $configuration = array_merge_recursive($configuration, $listener->toArray());
        }

        return $configuration;
    }
}
