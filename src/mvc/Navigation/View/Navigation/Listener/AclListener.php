<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\View\Navigation\Listener;

use Jield\Stdlib\Value;
use Laminas\EventManager\Event;
use Laminas\Navigation\Page\AbstractPage;
use Webmozart\Assert\Assert;

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
        $params = $event->getParams();
        $acl = $params['acl'];
        $page = $params['page'];
        $role = $params['role'];

        if (!$acl) {
            return $accepted;
        }

        $resource = Assert::isInstanceOf($page, AbstractPage::class)->getResource();
        $privilege = Assert::isInstanceOf($page, AbstractPage::class)->getPrivilege();

        if ($resource || $privilege) {
            $accepted =
                Value::truthy(Value::call($acl, 'hasResource', [$resource]))
                && Value::truthy(Value::call($acl, 'isAllowed', [$role, $resource, $privilege]));
        }

        return $accepted;
    }
}
