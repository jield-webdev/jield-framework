<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;


use Psr\Container\ContainerInterface;
use Jield\ApiTools\Hal\Extractor\LinkCollectionExtractor;
use Jield\ApiTools\Hal\Extractor\LinkExtractor;
use Laminas\ServiceManager\ServiceLocatorInterface;

class LinkCollectionExtractorFactory
{
    /**
     * @param ContainerInterface|ServiceLocatorInterface $container
     * @return LinkCollectionExtractor
     */
    public function __invoke(ServiceLocatorInterface|ContainerInterface $container): LinkCollectionExtractor
    {
        return new LinkCollectionExtractor(linkExtractor: $container->get(LinkExtractor::class));
    }
}
