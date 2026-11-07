<?php

declare(strict_types=1);

namespace Jield\Test\Util;

use Jield\ModuleManager\ModuleManager;
use Jield\Mvc\Application;
use Jield\Mvc\Service;
use Laminas\ServiceManager\ServiceManager;

use function is_numeric;

class ModuleLoader
{
    /** @var ServiceManager */
    protected $serviceManager;

    /**
     * Load list of modules or application configuration
     */
    public function __construct(array $configuration)
    {
        if (!isset($configuration['modules'])) {
            $modules = $configuration;
            $configuration = [
                'module_listener_options' => [
                    'module_paths' => [],
                ],
                'modules' => [
                    'Laminas\Router',
                    'Laminas\Validator',
                ],
            ];
            foreach ($modules as $key => $module) {
                if (is_numeric($key)) {
                    $configuration['modules'][] = $module;
                    continue;
                }
                $configuration['modules'][] = $key;
                $configuration['module_listener_options']['module_paths'][$key] = $module;
            }
        }

        $smConfig = $configuration['service_manager'] ?? [];
        $this->serviceManager = new ServiceManager();
        new Service\ServiceManagerConfig($smConfig)->configureServiceManager($this->serviceManager);
        $this->serviceManager->setService('ApplicationConfig', $configuration);
        \Jield\Stdlib\Service::get(
            $this->serviceManager,
            \Jield\ModuleManager\ModuleManager::class,
            'ModuleManager',
        )->loadModules();
    }

    /**
     * Get the application
     *
     * @return Application
     */
    public function getApplication()
    {
        return \Jield\Stdlib\Service::get($this->getServiceManager(), \Jield\Mvc\Application::class, 'Application');
    }

    /**
     * Get the module manager
     *
     * @return ModuleManager
     */
    public function getModuleManager()
    {
        return \Jield\Stdlib\Service::get(
            $this->getServiceManager(),
            \Jield\ModuleManager\ModuleManager::class,
            'ModuleManager',
        );
    }

    /**
     * Get module by name
     *
     * @param string $moduleName
     * @return mixed
     */
    public function getModule($moduleName)
    {
        return $this->getModuleManager()->getModule($moduleName);
    }

    /**
     * Get the service manager
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }
}
