<?php

declare(strict_types=1);

namespace Jield\Search\Message\Handler;

use Doctrine\ORM\EntityManager;
use Jield\Search\Entity\HasSearchInterface;
use Jield\Search\Message\UpdateSearchIndexMessage;
use Jield\Search\Service\AbstractSearchService;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final readonly class UpdateSearchIndexHandler
{
    public function __construct(
        private ContainerInterface $container,
        private EntityManager $entityManager,
    ) {
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Exception
     */
    public function __invoke(UpdateSearchIndexMessage $updateSearchIndexMessage): int
    {
        $entityClassName = $updateSearchIndexMessage->getEntityClassName();
        $searchServices = $updateSearchIndexMessage->getSearchServices();

        //Clear the entity manager to always have fresh results
        $this->entityManager->clear();

        $output = new BufferedOutput();

        /** @mago-var mixed $searchService */
        foreach ($searchServices as $searchService) {
            //I have to tell the system which search service we have to take (to have the correct connection)
            /** @var AbstractSearchService $searchServiceInstance */
            $searchServiceInstance = $this->container->get(Assert::string($searchService));
            $searchServiceInstance->updateCollection(output: $output, entity: Assert::isInstanceOf(
                Value::instantiate($entityClassName),
                HasSearchInterface::class,
            ));
        }

        return 0;
    }
}
