<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\Extractor\LinkCollectionExtractor;
use Jield\ApiTools\Hal\Extractor\LinkExtractor;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Psr\Container\ContainerInterface;

class LinkCollectionExtractorFactory
{
    /**
     * @param ContainerInterface|ServiceLocatorInterface $container
     * @return LinkCollectionExtractor
     */
    public function __invoke(ServiceLocatorInterface|ContainerInterface $container): LinkCollectionExtractor
    {
        return new LinkCollectionExtractor(linkExtractor: Service::get($container, LinkExtractor::class));
    }
}
