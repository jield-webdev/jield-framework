<?php

namespace Jield\Mvc\Service;

use Laminas\Paginator\AdapterPluginManager as PaginatorPluginManager;

class PaginatorPluginManagerFactory extends AbstractPluginManagerFactory
{
    public const string PLUGIN_MANAGER_CLASS = PaginatorPluginManager::class;
}
