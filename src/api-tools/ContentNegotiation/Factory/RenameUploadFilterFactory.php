<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Jield\ApiTools\ContentNegotiation\Filter\RenameUpload;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Stdlib\RequestInterface;
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
            $filter->setRequest(request: Service::get(
                $container,
                RequestInterface::class,
                'Request',
            ));
        }

        return $filter;
    }
}
