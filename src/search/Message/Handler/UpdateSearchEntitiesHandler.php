<?php

declare(strict_types=1);

namespace Jield\Search\Message\Handler;

use Doctrine\ORM\EntityManager;
use Jield\Search\Entity\HasSearchInterface;
use Jield\Search\Message\UpdateSearchEntitiesMessage;
use Jield\Search\Service\AbstractSearchService;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final readonly class UpdateSearchEntitiesHandler
{
    public function __construct(
        private ContainerInterface $container,
        private EntityManager $entityManager,
    ) {
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \RuntimeException
     */
    public function __invoke(UpdateSearchEntitiesMessage $updateSearchEntitiesMessage): int
    {
        $entityClassName = $updateSearchEntitiesMessage->getEntityClassName();
        $entityIds = $updateSearchEntitiesMessage->getEntityIds();
        $searchServices = $updateSearchEntitiesMessage->getSearchServices();

        //Clear the entity manager to always have fresh results
        $this->entityManager->clear();

        //Grab the entities
        $entities = [];

        /** @mago-var mixed $entityId */
        foreach ($entityIds as $entityId) {
            $entity = $this->entityManager
                ->getRepository(className: \Jield\Stdlib\Value::classString($entityClassName))
                ->find(id: $entityId);

            Assert::isInstanceOf(value: $entity, class: HasSearchInterface::class);

            //We already know that the entities are all the same type
            $entities[] = $entity;
        }

        /** @mago-var mixed $searchService */
        foreach ($searchServices as $searchService) {
            //I have to tell the system which search service we have to take (to have the correct connection)
            /** @var AbstractSearchService $searchServiceInstance */
            $searchServiceInstance = $this->container->get(Assert::string($searchService));
            $searchServiceInstance->updateEntities(entities: $entities);
        }

        return 0;
    }
}
