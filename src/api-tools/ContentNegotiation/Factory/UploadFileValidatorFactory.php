<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Jield\ApiTools\ContentNegotiation\Validator\UploadFile;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Stdlib\RequestInterface;
use Override;
use Psr\Container\ContainerInterface;

class UploadFileValidatorFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UploadFile
    {
        $validator = new UploadFile(options: $options);
        if ($container->has('Request')) {
            $validator->setRequest(request: Service::get(
                $container,
                RequestInterface::class,
                'Request',
            ));
        }

        return $validator;
    }
}
