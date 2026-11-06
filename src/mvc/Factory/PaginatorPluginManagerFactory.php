<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Laminas\Paginator\AdapterPluginManager as PaginatorPluginManager;

class PaginatorPluginManagerFactory extends AbstractPluginManagerFactory
{
    public const string PLUGIN_MANAGER_CLASS = PaginatorPluginManager::class;
}
