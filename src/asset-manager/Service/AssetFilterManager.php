<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Assetic\Contracts\Asset\AssetInterface;
use Assetic\Contracts\Filter\FilterInterface;
use Jield\AssetManager\Exception;
use Jield\AssetManager\Resolver\MimeResolverAwareInterface;
use Override;
use Psr\Container\ContainerInterface;

class AssetFilterManager implements MimeResolverAwareInterface
{
    protected ContainerInterface $container;

    protected MimeResolver $mimeResolver;

    /**
     * @var FilterInterface[] Filters already instantiated
     */
    protected array $filterInstances = [];

    /**
     * Construct the AssetFilterManager
     */
    public function __construct(
        protected array $config = [],
    ) {}

    /**
     * Get the filter configuration.
     */
    protected function getConfig(): array
    {
        return $this->config;
    }

    /**
     * See if there are filters for the asset, and if so, set them.
     *
     * @param string $path
     * @param \Jield\AssetManager\Asset\AssetInterface $asset
     *
     * @throws Exception\RuntimeException on invalid filters
     */
    public function setFilters(string $path, \Jield\AssetManager\Asset\AssetInterface $asset): void
    {
        $config = $this->getConfig();

        if (!empty($config[$path])) {
            /** @var mixed $filters */
            $filters = $config[$path];
        } elseif (!empty($config[$asset->getMimeType()])) {
            /** @var mixed $filters */
            $filters = $config[$asset->getMimeType()];
        } else {
            /** @var mixed $extension */
            $extension = $this->getMimeResolver()->getExtension(mimetype: \Jield\Stdlib\Value::string($asset->getMimeType()));
            if (!empty($config[$extension])) {
                /** @var mixed $filters */
                $filters = $config[$extension];
            } else {
                return;
            }
        }

        /** @var mixed $filter */
        foreach (\Jield\Stdlib\Value::iterable($filters) as $filter) {
            if (is_null(value: $filter)) {
                continue;
            }

            if (!empty($filter['filter'])) {
                $this->ensureByFilter(asset: $asset, filter: \Jield\Stdlib\Value::instanceOrString($filter['filter'], \Assetic\Contracts\Filter\FilterInterface::class));
            } elseif (!empty($filter['service'])) {
                $this->ensureByService(asset: $asset, service: \Jield\Stdlib\Value::string(\Jield\Stdlib\Value::accessible($filter)['service']));
            } else {
                throw new Exception\RuntimeException(message: 'Invalid filter supplied. Expected Filter or Service.');
            }
        }
    }

    /**
     * Ensure that the filters as service are set.
     *
     * @param AssetInterface $asset
     * @param string $service A valid service name.
     * @throws  Exception\RuntimeException
     */
    protected function ensureByService(AssetInterface $asset, string $service): void
    {
        $this->ensureByFilter(asset: $asset, filter: \Jield\Stdlib\Value::instanceOrString($this->container->get($service), \Assetic\Contracts\Filter\FilterInterface::class));
    }

    /**
     * Ensure that the filters as filter are set.
     *
     * @param AssetInterface $asset
     * @param string|FilterInterface $filter Either an instance of FilterInterface or a classname.
     * @throws Exception\RuntimeException
     */
    protected function ensureByFilter(AssetInterface $asset, string|FilterInterface $filter): void
    {
        if ($filter instanceof FilterInterface) {
            $filterInstance = $filter;
            $asset->ensureFilter(filter: $filterInstance);

            return;
        }

        $filterClass = $filter;

        if (!is_subclass_of(object_or_class: $filterClass, class: FilterInterface::class, allow_string: true)) {
            $filterClass .= str_ends_with(haystack: $filterClass, needle: 'Filter') ? '' : 'Filter';
            $filterClass = 'Assetic\Filter\\' . $filterClass;
        }

        if (!class_exists(class: $filterClass)) {
            throw new Exception\RuntimeException(message: 'No filter found for ' . $filter);
        }

        if (!isset($this->filterInstances[$filterClass])) {
            $this->filterInstances[$filterClass] = \Jield\Stdlib\Value::instantiate($filterClass);
        }

        $filterInstance = $this->filterInstances[$filterClass];

        $asset->ensureFilter(filter: \Jield\Stdlib\Value::instance($filterInstance, \Assetic\Contracts\Filter\FilterInterface::class));
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    #[Override]
    public function getMimeResolver(): MimeResolver
    {
        return $this->mimeResolver;
    }

    #[Override]
    public function setMimeResolver(MimeResolver $mimeResolver): void
    {
        $this->mimeResolver = $mimeResolver;
    }
}
