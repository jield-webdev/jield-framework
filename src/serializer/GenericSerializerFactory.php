<?php

declare(strict_types=1);

namespace Jield\Serializer;

use Jield\Serializer\Adapter\AdapterInterface;
use Psr\Container\ContainerInterface;

use function assert;

final class GenericSerializerFactory
{
    /**
     * @param class-string<AdapterInterface> $serializerName
     * @param array<string,mixed>|null       $options
     */
    public function __construct(private readonly string $serializerName, private readonly array|null $options = null)
    {
    }

    public function __invoke(ContainerInterface $container): AdapterInterface
    {
        $plugins = $container->get(AdapterPluginManager::class);
        assert(assertion: $plugins instanceof AdapterPluginManager);

        return $plugins->build(name: $this->serializerName, options: $this->options);
    }
}
