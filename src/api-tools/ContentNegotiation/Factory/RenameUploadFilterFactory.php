<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Override;
use Psr\Container\ContainerInterface;
use Jield\ApiTools\ContentNegotiation\Filter\RenameUpload;
use Laminas\ServiceManager\Factory\FactoryInterface;

class RenameUploadFilterFactory implements FactoryInterface
{
    /**
     * @param string $requestedName ,
     */
    #[Override]
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): RenameUpload
    {
        $filter = new RenameUpload(targetOrOptions: $options);

        if ($container->has(id: 'Request')) {
            $filter->setRequest(request: $container->get('Request'));
        }

        return $filter;
    }
}
