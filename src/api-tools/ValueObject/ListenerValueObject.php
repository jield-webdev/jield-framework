<?php

declare(strict_types=1);

namespace Jield\ApiTools\ValueObject;

use Jield\ApiTools\Enum\RouteMethodEnum;
use Jield\ApiTools\Enum\RouteTypeEnum;
use Jield\Authorize\Guard\Route;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Webmozart\Assert\Assert;

final readonly class ListenerValueObject
{
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:excessive-parameter-list(1)
    public function __construct(
        private string $listener,
        private string $route,
        private RouteTypeEnum $routeType = RouteTypeEnum::COLLECTION,
        private RouteMethodEnum $routeMethod = RouteMethodEnum::GET,
        private array $configAbstractFactories = [],
        private array $entityCollectionWhiteList = [],
        private int $pageSize = 30,
        private int $maxPageSize = 9999,
        private array $inputFilterSpecification = [],
        private ?string $routeAssertionClass = null,
        private ?string $privilege = null,
    ) {
        Assert::false($this->pageSize > $this->maxPageSize, 'Page size cannot be greater than max page size');
    }

    public function toArray(): array
    {
        $return = [
            'api-tools-rest' => $this->getApiToolsRestConfig(),
            'api-tools-mvc-auth' => [
                'authorization' => $this->getApiToolsMvcConfig(),
            ],
            'router' => [
                'routes' => $this->getRouteConfig(),
            ],
            'bjyauthorize' => [
                'guards' => [
                    Route::class => [
                        $this->getGuardConfig(),
                    ],
                ],
            ],
            'service_manager' => [
                'factories' => [
                    $this->listener => ConfigAbstractFactory::class,
                ],
            ],
            ConfigAbstractFactory::class => [
                $this->listener => $this->configAbstractFactories,
            ],
        ];

        //Only add the inputfilter if this is set
        if ($this->inputFilterSpecification !== []) {
            $return['api-tools-content-validation'] = [
                $this->listener => [
                    'input_filter' => $this->listener,
                ],
            ];
            $return['input_filter_specs'] = [
                $this->listener => $this->inputFilterSpecification,
            ];
        }

        return $return;
    }

    public function getApiToolsRestConfig(): array
    {
        //Depending on the route type, we will have different configurations
        $config = match ($this->routeType) {
            RouteTypeEnum::ENTITY => [
                'entity_http_methods' => [$this->routeMethod->value],
            ],
            RouteTypeEnum::COLLECTION => [
                'collection_http_methods' => [$this->routeMethod->value],
                'page_size' => $this->pageSize,
                'page_size_param' => 'page_size',
                'max_page_size' => $this->maxPageSize,
                'collection_query_whitelist' => array_merge([
                    'filter',
                    'query',
                    'order',
                    'direction',
                ], $this->entityCollectionWhiteList),
            ],
        };

        return [
            $this->listener => array_merge([
                'listener' => $this->listener,
                'route_name' => $this->listener,
                'route_identifier_name' => 'id',
            ], $config),
        ];
    }

    public function getApiToolsMvcConfig(): array
    {
        return [
            $this->listener => [
                $this->routeType->value => [
                    $this->routeMethod->value => true,
                ],
            ],
        ];
    }

    private function getRouteConfig(): array
    {
        $privilege = $this->privilege;
        if (!$privilege) {
            $privilege = $this->routeMethod === RouteMethodEnum::GET ? 'view' : 'edit';
        }

        return [
            $this->listener => [
                'type' => str_contains(haystack: $this->route, needle: ':') ? Segment::class : Literal::class,
                'options' => [
                    'route' => $this->route,
                    'defaults' => [
                        'controller' => $this->listener,
                        'privilege' => $privilege,
                    ],
                ],
            ],
        ];
    }

    private function getGuardConfig(): array
    {
        if (null !== $this->routeAssertionClass) {
            return [
                'route' => $this->listener,
                'roles' => [],
                'assertion' => $this->routeAssertionClass,
            ];
        }

        return [
            'route' => $this->listener,
            'roles' => [],
        ];
    }
}
