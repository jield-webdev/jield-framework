<?php

namespace Jield\Mvc\Factory;

use Jield\Mvc\Controller\PluginManager as ControllerPluginManager;

class ControllerPluginManagerFactory extends AbstractPluginManagerFactory
{
    public const string PLUGIN_MANAGER_CLASS = ControllerPluginManager::class;
}
