<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\Link\LinkUrlBuilder;
use Jield\Mvc\View\Helper\ServerUrl;
use Jield\Mvc\View\Helper\Url;
use Laminas\ServiceManager\ServiceLocatorInterface;
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
        /** @var mixed $halConfig */
        $halConfig = $container->get('Jield\ApiTools\Hal\HalConfig');

        $viewHelperManager = \Jield\Stdlib\Service::get($container, \Laminas\View\HelperPluginManager::class, 'ViewHelperManager');

        $serverUrlHelper = $viewHelperManager->get('ServerUrl');
        Assert::isInstanceOf(value: $serverUrlHelper, class: ServerUrl::class);

        if (isset($halConfig['options']['use_proxy'])) {
            $serverUrlHelper->setUseProxy(\Jield\Stdlib\Value::bool($halConfig['options']['use_proxy']));
        }

        $urlHelper = $viewHelperManager->get('Url');
        Assert::isInstanceOf(value: $urlHelper, class: Url::class);

        return new LinkUrlBuilder(serverUrlHelper: $serverUrlHelper, urlHelper: $urlHelper);
    }
}
