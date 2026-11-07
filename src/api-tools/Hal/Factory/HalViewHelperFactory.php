<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\Exception;
use Jield\ApiTools\Hal\Extractor\LinkCollectionExtractor;
use Jield\ApiTools\Hal\Link;
use Jield\ApiTools\Hal\Metadata\MetadataMap;
use Jield\ApiTools\Hal\Plugin;
use Jield\ApiTools\Hal\RendererOptions;
use Jield\Stdlib\Service;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Hydrator\HydratorPluginManager;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function sprintf;

class HalViewHelperFactory
{
    /**
     * @throws Exception\DomainException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Plugin\Hal
    {
        $rendererOptions = Service::get($container, RendererOptions::class);
        $metadataMap = Service::get($container, MetadataMap::class);

        $hydrators = $metadataMap->getHydratorManager();
        Assert::isInstanceOf(value: $hydrators, class: HydratorPluginManager::class);

        $helper = new Plugin\Hal(hydrators: $hydrators);

        if ($container->has('EventManager')) {
            $eventManager = Service::get(
                $container,
                \Laminas\EventManager\EventManagerInterface::class,
                'EventManager',
            );
            $helper->setEventManager(eventManager: $eventManager);
        }

        $helper->setMetadataMap(map: $metadataMap);
        $linkUrlBuilder = Service::get($container, Link\LinkUrlBuilder::class);
        $helper->setLinkUrlBuilder(builder: $linkUrlBuilder);

        $linkCollectionExtractor = Service::get($container, LinkCollectionExtractor::class);
        $helper->setLinkCollectionExtractor(extractor: $linkCollectionExtractor);

        $defaultHydrator = $rendererOptions->getDefaultHydrator();
        if (null !== $defaultHydrator) {
            if (!$hydrators->has($defaultHydrator)) {
                throw new Exception\DomainException(message: sprintf(
                    'Cannot locate default hydrator by name "%s" via the HydratorManager',
                    $defaultHydrator,
                ));
            }

            /** @mago-var mixed $hydrator */
            $hydrator = $hydrators->get($defaultHydrator);
            Assert::isInstanceOf(value: $hydrator, class: HydratorInterface::class);
            $helper->setDefaultHydrator(hydrator: $hydrator);
        }

        $helper->setRenderEmbeddedEntities(value: $rendererOptions->getRenderEmbeddedEntities());
        $helper->setRenderCollections(value: $rendererOptions->getRenderEmbeddedCollections());

        $hydratorMap = $rendererOptions->getHydrators();
        /** @mago-var mixed $hydratorServiceName */
        foreach ($hydratorMap as $class => $hydratorServiceName) {
            $helper->addHydrator(class: Assert::string($class), hydrator: Assert::isInstanceOf($hydratorServiceName, \Laminas\Hydrator\ExtractionInterface::class));
        }

        return $helper;
    }
}
