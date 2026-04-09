<?php

namespace Jield\Bootstrap5\View\Helper;

use Laminas\View\Helper\Navigation as JieldNavigation;
use Jield\Bootstrap5\View\Helper;

/**
 * Class Navigation
 * @package Jield\Bootstrap5\View\Helper
 */
class Navigation extends JieldNavigation
{
    protected string $defaultProxy = 'lbs5menu';

    protected array $defaultPluginManagerHelpers
        = [
            'lbs5menu' => Helper\Navigation\Menu::class,
        ];

    public function getPluginManager(): JieldNavigation\PluginManager
    {
        $pm = parent::getPluginManager();
        foreach ($this->defaultPluginManagerHelpers as $name => $invokableClass) {
            $pm->setAllowOverride(true);
            $pm->setInvokableClass($name, $invokableClass);
        }

        return $pm;
    }
}
