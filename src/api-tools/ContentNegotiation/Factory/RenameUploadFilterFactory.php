<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Jield\ApiTools\ContentNegotiation\Filter\RenameUpload;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class RenameUploadFilterFactory implements FactoryInterface
{
    /**
     * @param string $requestedName ,
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RenameUpload
    {
        $filter = new RenameUpload(targetOrOptions: $options);

        if ($container->has('Request')) {
            $filter->setRequest(request: \Jield\Stdlib\Service::get($container, \Laminas\Stdlib\RequestInterface::class, 'Request'));
        }

        return $filter;
    }
}
