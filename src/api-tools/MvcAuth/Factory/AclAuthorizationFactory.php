<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authorization\AclAuthorization;
use Jield\ApiTools\MvcAuth\Authorization\AclAuthorizationFactory as AclFactory;
use Jield\Stdlib\Value;
use Laminas\Http\Request;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_keys;
use function lcfirst;
use function sprintf;
use function strtr;

/**
 * Factory for creating an AclAuthorization instance from configuration
 */
class AclAuthorizationFactory implements FactoryInterface
{
    /** @var array */
    protected array $httpMethods = [
        Request::METHOD_DELETE => true,
        Request::METHOD_GET => true,
        Request::METHOD_PATCH => true,
        Request::METHOD_POST => true,
        Request::METHOD_PUT => true,
    ];

    /**
     * Create and return an AclAuthorization instance.
     *
     * @param string $requestedName
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AclAuthorization
    {
        $config = $this->getConfigFromContainer(container: $container);
        return $this->createAclFromConfig(config: $config);
    }

    /**
     * Generate the ACL instance based on the api-tools-mvc-auth "authorization" configuration
     *
     * Consumes the AclFactory in order to create the AclAuthorization instance.
     *
     * @param array $config
     * @return AclAuthorization
     */
    protected function createAclFromConfig(array $config): AclAuthorization
    {
        $aclConfig = [];
        $denyByDefault = false;

        if (array_key_exists(key: 'deny_by_default', array: $config)) {
            $denyByDefault = $aclConfig['deny_by_default'] = Value::truthy($config['deny_by_default']);
            unset($config['deny_by_default']);
        }

        /** @mago-var mixed $privileges */
        foreach ($config as $controllerService => $privileges) {
            $this->createAclConfigFromPrivileges(
                controllerService: Assert::string($controllerService),
                privileges: Assert::isArray($privileges),
                aclConfig: $aclConfig,
                denyByDefault: $denyByDefault,
            );
        }

        return AclFactory::factory(config: $aclConfig);
    }

    /**
     * Creates ACL configuration based on the privileges configured
     *
     * - Extracts a privilege per action
     * - Extracts privileges for each of "collection" and "entity" configured
     *
     * @param string $controllerService
     * @param array $privileges
     * @param array $aclConfig
     * @param bool $denyByDefault
     */
    protected function createAclConfigFromPrivileges(
        string $controllerService,
        array $privileges,
        array &$aclConfig,
        bool $denyByDefault,
    ): void {
        // Normalize the controller service name.
        // laminas-mvc will always pass the name using namespace seprators, but
        // the admin may write the name using dash seprators.
        $controllerService = strtr($controllerService, '-', '\\');
        if (isset($privileges['actions'])) {
            /**
             * @mago-var mixed $action
             * @mago-var mixed $methods
             */
            foreach (Assert::isIterable($privileges['actions']) as $action => $methods) {
                $action = lcfirst(string: (string) $action);
                $aclConfig[] = [
                    'resource' => sprintf('%s::%s', $controllerService, $action),
                    'privileges' => $this->createPrivilegesFromMethods(
                        methods: Assert::isArray($methods),
                        denyByDefault: $denyByDefault,
                    ),
                ];
            }
        }

        if (isset($privileges['collection'])) {
            $aclConfig[] = [
                'resource' => sprintf('%s::collection', $controllerService),
                'privileges' => $this->createPrivilegesFromMethods(
                    methods: Assert::isArray($privileges['collection']),
                    denyByDefault: $denyByDefault,
                ),
            ];
        }

        if (isset($privileges['entity'])) {
            $aclConfig[] = [
                'resource' => sprintf('%s::entity', $controllerService),
                'privileges' => $this->createPrivilegesFromMethods(
                    methods: Assert::isArray($privileges['entity']),
                    denyByDefault: $denyByDefault,
                ),
            ];
        }
    }

    /**
     * Create the list of HTTP methods defining privileges
     *
     * @param array $methods
     * @param bool $denyByDefault
     * @return array|null
     */
    protected function createPrivilegesFromMethods(array $methods, bool $denyByDefault): ?array
    {
        $privileges = [];

        if (isset($methods['default']) && Value::truthy($methods['default'])) {
            $privileges = $this->httpMethods;
            unset($methods['default']);
        }

        /** @mago-var mixed $flag */
        foreach ($methods as $method => $flag) {
            // If the flag evaluates true and we're denying by default, OR
            // if the flag evaluates false and we're allowing by default,
            // THEN no rule needs to be added
            if ($denyByDefault && Value::truthy($flag) || !$denyByDefault && !$flag) {
                if (isset($privileges[$method])) {
                    unset($privileges[$method]);
                }

                continue;
            }

            // Otherwise, we need to add a rule
            $privileges[$method] = true;
        }

        if (empty($privileges)) {
            return null;
        }

        return array_keys(array: $privileges);
    }

    /**
     * Retrieve configuration from the container.
     *
     * Attempts to pull the 'config' service, and, further, the
     * api-tools-mvc-auth.authorization segment.
     *
     */
    private function getConfigFromContainer(ContainerInterface $container): array
    {
        if (!$container->has('config')) {
            return [];
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');

        if (!isset($config['api-tools-mvc-auth']['authorization'])) {
            return [];
        }

        return Assert::isArray($config['api-tools-mvc-auth']['authorization']);
    }
}
