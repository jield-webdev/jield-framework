<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use function array_key_exists;
use function is_array;

// phpcs:ignore WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix
abstract class AclAuthorizationFactory
{
    /**
     * Create and return an AclAuthorization instance populated with provided privileges.
     *
     * @param array $config
     * @return AclAuthorization
     */
    public static function factory(array $config): AclAuthorization
    {
        // Determine whether we are whitelisting or blacklisting
        $denyByDefault = false;
        if (array_key_exists(key: 'deny_by_default', array: $config)) {
            $denyByDefault = (bool) $config['deny_by_default'];
            unset($config['deny_by_default']);
        }

        // By default, create an open ACL
        $acl = new AclAuthorization();
        $acl->addRole('guest');
        $acl->allow();

        $grant = 'deny';
        if ($denyByDefault) {
            $acl->deny('guest', null, null);
            $grant = 'allow';
        }

        if ($config !== []) {
            return self::injectGrants(acl: $acl, grantType: $grant, rules: $config);
        }

        return $acl;
    }

    /**
     * Inject the ACL with the grants specified in the collection of rules.
     *
     * @param string $grantType Either "allow" or "deny".
     */
    private static function injectGrants(AclAuthorization $acl, string $grantType, array $rules): AclAuthorization
    {
        foreach ($rules as $set) {
            if (! is_array(value: $set) || ! isset($set['resource'])) {
                continue;
            }

            self::injectGrant(acl: $acl, grantType: $grantType, ruleSet: $set);
        }

        return $acl;
    }

    /**
     * Inject the ACL with the grant specified by a single rule set.
     *
     * @param string $grantType
     */
    private static function injectGrant(AclAuthorization $acl, string $grantType, array $ruleSet): void
    {
        // Add new resource to ACL
        $resource = $ruleSet['resource'];
        $acl->addResource($ruleSet['resource']);

        // Deny guest specified privileges to resource
        $privileges = $ruleSet['privileges'] ?? null;

        // null privileges means no permissions were setup; nothing to do
        if (null === $privileges) {
            return;
        }

        $acl->$grantType('guest', $resource, $privileges);
    }
}
