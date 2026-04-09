<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;


use Psr\Container\ContainerInterface;
use Jield\ApiTools\Hal\Extractor\LinkExtractor;
use Jield\ApiTools\Hal\Link\LinkUrlBuilder;
use Laminas\ServiceManager\ServiceLocatorInterface;

class LinkExtractorFactory
{
    /**
     * @param ContainerInterface|ServiceLocatorInterface $container
     * @return LinkExtractor
     */
    public function __invoke(ServiceLocatorInterface|ContainerInterface $container): LinkExtractor
    {
        return new LinkExtractor(linkUrlBuilder: $container->get(LinkUrlBuilder::class));
    }
}
