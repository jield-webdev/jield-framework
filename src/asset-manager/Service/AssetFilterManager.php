<?php

declare(strict_types=1);

namespace Jield\AssetManager\Service;

use Assetic\Contracts\Asset\AssetInterface;
use Assetic\Contracts\Filter\FilterInterface;
use Jield\AssetManager\Exception;
use Jield\AssetManager\Resolver\MimeResolverAwareInterface;
use Jield\Stdlib\Value;
use Override;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
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
    ) {
    }

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
     *
     * @throws Exception\RuntimeException on invalid filters
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function setFilters(string $path, \Jield\AssetManager\Asset\AssetInterface $asset): void
    {
        $config = $this->getConfig();
        $mimeType = $asset->getMimeType() ?? '';

        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(2)
        if (!!($config[$path] ?? null)) {
            /** @mago-var mixed $filters */
            $filters = $config[$path];
        } elseif (!!($config[$mimeType] ?? null)) {
            /** @mago-var mixed $filters */
            $filters = $config[$mimeType];
        } else {
            /** @mago-var mixed $extension */
            $extension = $this->getMimeResolver()->getExtension(mimetype: $mimeType);
            if (!($config[$extension] ?? null)) {
                return;
            }
            /** @mago-var mixed $filters */
            $filters = $config[$extension];
        }

        /** @mago-var mixed $filter */
        foreach (Assert::isIterable($filters) as $filter) {
            if (is_null(value: $filter)) {
                continue;
            }
            if (!!($filter['filter'] ?? null)) {
                $this->ensureByFilter(asset: $asset, filter: Value::instanceOrString(
                    $filter['filter'] ?? null,
                    \Assetic\Contracts\Filter\FilterInterface::class,
                ));
                continue;
            }
            if (!!($filter['service'] ?? null)) {
                $this->ensureByService(asset: $asset, service: Assert::string(Value::accessible($filter)['service']));
                continue;
            }
            throw new Exception\RuntimeException(message: 'Invalid filter supplied. Expected Filter or Service.');
        }
    }

    /**
     * Ensure that the filters as service are set.
     *
     * @param string $service A valid service name.
     * @throws  Exception\RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    protected function ensureByService(AssetInterface $asset, string $service): void
    {
        $this->ensureByFilter(asset: $asset, filter: Value::instanceOrString(
            $this->container->get($service),
            \Assetic\Contracts\Filter\FilterInterface::class,
        ));
    }

    /**
     * Ensure that the filters as filter are set.
     *
     * @param string|FilterInterface $filter Either an instance of FilterInterface or a classname.
     * @throws Exception\RuntimeException
     * @throws \ReflectionException
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

        $this->filterInstances[$filterClass] ??= Assert::isInstanceOf(
            Value::instantiate($filterClass),
            FilterInterface::class,
        );

        $filterInstance = $this->filterInstances[$filterClass];

        $asset->ensureFilter(filter: $filterInstance);
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
