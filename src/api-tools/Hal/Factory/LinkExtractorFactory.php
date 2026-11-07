<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\Extractor\LinkExtractor;
use Jield\ApiTools\Hal\Link\LinkUrlBuilder;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Psr\Container\ContainerInterface;

class LinkExtractorFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ServiceLocatorInterface|ContainerInterface $container): LinkExtractor
    {
        return new LinkExtractor(linkUrlBuilder: Service::get($container, LinkUrlBuilder::class));
    }
}
