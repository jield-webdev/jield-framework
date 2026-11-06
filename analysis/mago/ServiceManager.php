<?php

// Analysis-only descriptions of the ServiceManager's callable configuration entries.
// Flatten the recursive callable aliases; the runtime definitions remain in vendor.
namespace Laminas\ServiceManager;

/**
 * @psalm-type DelegatorsConfiguration = array<string, array<array-key, class-string|Factory\DelegatorFactoryInterface|callable>>
 * @psalm-type FactoriesConfiguration = array<string, class-string|Factory\FactoryInterface|callable>
 * @psalm-type InitializersConfiguration = array<array-key, class-string|Initializer\InitializerInterface|callable>
 * @psalm-type ServiceManagerConfiguration = array{
 *     abstract_factories?: array<array-key, class-string<Factory\AbstractFactoryInterface>|Factory\AbstractFactoryInterface>,
 *     aliases?: array<string, string>,
 *     delegators?: DelegatorsConfiguration,
 *     factories?: FactoriesConfiguration,
 *     initializers?: InitializersConfiguration,
 *     invokables?: array<string, class-string>,
 *     lazy_services?: array{class_map?: array<string, class-string>, proxies_namespace?: non-empty-string, proxies_target_dir?: non-empty-string, write_proxy_files?: bool},
 *     services?: array<string, mixed>,
 *     shared?: array<string, bool>,
 *     shared_by_default?: bool,
 *     ...<string, mixed>
 * }
 */
class ServiceManager
{
}
