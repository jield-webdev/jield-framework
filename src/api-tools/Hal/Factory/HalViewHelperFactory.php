<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;


use Jield\ApiTools\Hal\Exception;
use Jield\ApiTools\Hal\Extractor\LinkCollectionExtractor;
use Jield\ApiTools\Hal\Link;
use Jield\ApiTools\Hal\Metadata\MetadataMap;
use Jield\ApiTools\Hal\Plugin;
use Jield\ApiTools\Hal\RendererOptions;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Hydrator\HydratorPluginManager;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;
use function sprintf;

class HalViewHelperFactory
{
    public function __invoke(ContainerInterface $container): Plugin\Hal
    {
        /** @var RendererOptions $rendererOptions */
        $rendererOptions = $container->get(RendererOptions::class);
        Assert::isInstanceOf(value: $rendererOptions, class: RendererOptions::class);
        $metadataMap = $container->get(MetadataMap::class);
        Assert::isInstanceOf(value: $metadataMap, class: MetadataMap::class);

        $hydrators = $metadataMap->getHydratorManager();
        Assert::isInstanceOf(value: $hydrators, class: HydratorPluginManager::class);

        $helper = new Plugin\Hal(hydrators: $hydrators);

        if ($container->has(id: 'EventManager')) {
            $eventManager = $container->get('EventManager');
            Assert::isInstanceOf(value: $eventManager, class: EventManagerInterface::class);
            $helper->setEventManager(eventManager: $eventManager);
        }

        $helper->setMetadataMap(map: $metadataMap);
        $linkUrlBuilder = $container->get(Link\LinkUrlBuilder::class);
        Assert::isInstanceOf(value: $linkUrlBuilder, class: Link\LinkUrlBuilder::class);
        $helper->setLinkUrlBuilder(builder: $linkUrlBuilder);

        $linkCollectionExtractor = $container->get(LinkCollectionExtractor::class);
        Assert::isInstanceOf(value: $linkCollectionExtractor, class: LinkCollectionExtractor::class);
        $helper->setLinkCollectionExtractor(extractor: $linkCollectionExtractor);

        $defaultHydrator = $rendererOptions->getDefaultHydrator();
        if (null !== $defaultHydrator) {
            if (!$hydrators->has(id: $defaultHydrator)) {
                throw new Exception\DomainException(message: sprintf(
                    'Cannot locate default hydrator by name "%s" via the HydratorManager',
                    $defaultHydrator
                ));
            }

            $hydrator = $hydrators->get(id: $defaultHydrator);
            Assert::isInstanceOf(value: $hydrator, class: HydratorInterface::class);
            $helper->setDefaultHydrator(hydrator: $hydrator);
        }

        $helper->setRenderEmbeddedEntities(value: $rendererOptions->getRenderEmbeddedEntities());
        $helper->setRenderCollections(value: $rendererOptions->getRenderEmbeddedCollections());

        $hydratorMap = $rendererOptions->getHydrators();
        foreach ($hydratorMap as $class => $hydratorServiceName) {
            $helper->addHydrator(class: $class, hydrator: $hydratorServiceName);
        }

        return $helper;
    }
}
