<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\Link\LinkUrlBuilder;
use Jield\Mvc\View\Helper\ServerUrl;
use Jield\Mvc\View\Helper\Url;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class LinkUrlBuilderFactory
{
    /**
     * @param ContainerInterface|ServiceLocatorInterface $container
     * @return LinkUrlBuilder
     */
    public function __invoke(ServiceLocatorInterface|ContainerInterface $container): LinkUrlBuilder
    {
        /** @mago-var mixed $halConfig */
        $halConfig = $container->get('Jield\ApiTools\Hal\HalConfig');

        $viewHelperManager = Service::get($container, HelperPluginManager::class, 'ViewHelperManager');

        $serverUrlHelper = $viewHelperManager->get('ServerUrl');
        Assert::isInstanceOf(value: $serverUrlHelper, class: ServerUrl::class);

        if (isset($halConfig['options']['use_proxy'])) {
            $serverUrlHelper->setUseProxy(Assert::boolean($halConfig['options']['use_proxy']));
        }

        $urlHelper = $viewHelperManager->get('Url');
        Assert::isInstanceOf(value: $urlHelper, class: Url::class);

        return new LinkUrlBuilder(serverUrlHelper: $serverUrlHelper, urlHelper: $urlHelper);
    }
}
