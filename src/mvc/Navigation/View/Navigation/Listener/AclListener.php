<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\View\Navigation\Listener;

use Laminas\EventManager\Event;

/**
 * Default Access Control Listener
 */
class AclListener
{
    /**
     * Determines whether a page should be accepted by ACL when iterating
     *
     * - If helper has no ACL, page is accepted
     * - If page has a resource or privilege defined, page is accepted if the
     *   ACL allows access to it using the helper's role
     * - If page has no resource or privilege, page is accepted
     * - If helper has ACL and role:
     *      - Page is accepted if it has no resource or privilege.
     *      - Page is accepted if ACL allows page's resource or privilege.
     *
     * @return bool
     */
    public static function accept(Event $event)
    {
        $accepted = true;
        $params   = $event->getParams();
        /** @var mixed $acl */
        $acl      = $params['acl'];
        /** @var mixed $page */
        $page     = $params['page'];
        /** @var mixed $role */
        $role     = $params['role'];

        if (!$acl) {
            return $accepted;
        }

        $resource  = \Jield\Stdlib\Value::instance($page, \Laminas\Navigation\Page\AbstractPage::class)->getResource();
        $privilege = \Jield\Stdlib\Value::instance($page, \Laminas\Navigation\Page\AbstractPage::class)->getPrivilege();

        if ($resource || $privilege) {
            $accepted = \Jield\Stdlib\Value::truthy(\Jield\Stdlib\Value::call($acl, 'hasResource', [$resource]))
                && \Jield\Stdlib\Value::truthy(\Jield\Stdlib\Value::call($acl, 'isAllowed', [$role, $resource, $privilege]));
        }

        return $accepted;
    }
}
