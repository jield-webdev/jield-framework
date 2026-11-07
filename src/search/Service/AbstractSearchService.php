<?php

declare(strict_types=1);

namespace Jield\Search\Service;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManager;
use Jield\Search\Document\DocumentHelperInterface;
use Jield\Search\Entity\HasSearchInterface;
use Jield\Search\ValueObject\FacetField;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Translator\TranslatorInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Solarium\Client;
use Solarium\Component\Facet\Field;
use Solarium\Component\FacetSet;
use Solarium\Core\Client\Adapter\Http;
use Solarium\Core\Query\DocumentInterface;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Document;
use Solarium\QueryType\Update\Result;
use stdClass;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Webmozart\Assert\Assert;

use function defined;
use function sprintf;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
abstract class AbstractSearchService implements SearchServiceInterface
{
    final public const string DATE_SOLR = 'Y-m-d\TH:i:s\Z';
    final public const int QUERY_TERM_BOOST = 30;
    public const string SOLR_CONNECTION = 'default';

    protected ?Client $solrClient = null;

    protected ?Query $query = null;

    protected ?FacetSet $facetSet = null;

    /** @var array<string, FacetField> */
    protected array $facets = [];

    protected readonly TranslatorInterface $translator;

    protected readonly EntityManager $entityManager;

    private array $config;

    private string $connection;

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    public function __construct(
        protected readonly ContainerInterface $container,
    ) {
        $this->translator = Service::get($container, TranslatorInterface::class);
        $this->entityManager = Service::get($container, EntityManager::class);
        $this->config = Assert::isArray($container->get('Config'));

        $this->query = $this->getSolrClient()->createSelect();
        $this->facetSet = $this->query->getFacetSet();
    }

    /**
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    public function getSolrClient(): Client
    {
        if (null === $this->solrClient && defined(constant_name: 'static::SOLR_CONNECTION')) {
            /** @mago-var mixed $prefix */
            $prefix = $this->config['solr']['prefix'] ?? '';
            $connection = static::SOLR_CONNECTION;
            if ($prefix) {
                $connection = sprintf('%s_%s', Value::scalar($prefix), $connection);
            }

            $this->connection = $connection;

            /** @mago-var mixed $params */
            $params = $this->config['solr']['connection'][$connection] ?? [];

            //Only change the core when this is different from the already given core
            if (($params['endpoint']['server']['core'] ?? null) === null) {
                Value::setPath($params, ['endpoint', 'server', 'core'], $connection);
            }

            if (($this->config['solr']['host'] ?? null) !== null) {
                Value::setPath($params, ['endpoint', 'server', 'host'], $this->config['solr']['host']);
            }

            if (($this->config['solr']['username'] ?? null) !== null) {
                Value::setPath($params, ['endpoint', 'server', 'username'], $this->config['solr']['username']);
                Value::setPath($params, ['endpoint', 'server', 'password'], $this->config['solr']['password']);
            }

            $adapter = new Http();

            if (
                ($this->config['solr']['timeout'] ?? null) !== null
                && Value::truthy($this->config['solr']['timeout'])
            ) {
                $adapter->setTimeout(timeoutInSeconds: (int) $this->config['solr']['timeout']);
            }

            $eventDispatcher = new EventDispatcher();

            $this->solrClient = new Client(
                adapter: $adapter,
                eventDispatcher: $eventDispatcher,
                options: Assert::isArray($params),
            );
            $this->solrClient->getPlugin(key: 'postbigrequest');
        }

        return Assert::isInstanceOf($this->solrClient, \Solarium\Client::class);
    }

    /**
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function updateEntity(HasSearchInterface $entity): void
    {
        $update = $this->getSolrClient()->createUpdate();

        $document = $this->getSearchDocumentFromEntity(update: $update, entity: $entity);

        $update->addDocument(document: $document);
        $update->addCommit();

        $this->getSolrClient()->update(query: $update);
    }

    /**
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getSearchDocumentFromEntity(
        \Solarium\QueryType\Update\Query\Query $update,
        HasSearchInterface $entity,
    ): DocumentInterface {
        //We need the helper to create the document for search
        if (!$this->container->has($entity->getSearchDocumentClass())) {
            throw new RuntimeException(
                message: 'No search document helper ('
                . $entity->getSearchDocumentClass()
                . ') registered for '
                . $entity::class
                . ', did you register it in the service manager?',
            );
        }

        /** @var DocumentHelperInterface $searchDocumentHelper */
        $searchDocumentHelper = $this->container->get($entity->getSearchDocumentClass());

        Assert::implementsInterface(value: $searchDocumentHelper, interface: DocumentHelperInterface::class);

        return $searchDocumentHelper->getDocument(
            document: Assert::isInstanceOf($update->createDocument(), \Solarium\QueryType\Update\Query\Document::class),
            entity: $entity,
        );
    }

    /**
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function updateEntities(array|Collection $entities): void
    {
        Assert::allImplementsInterface(value: $entities, interface: HasSearchInterface::class);

        $update = $this->getSolrClient()->createUpdate();

        foreach ($entities as $entity) {
            $document = $this->getSearchDocumentFromEntity(update: $update, entity: Assert::isInstanceOf(
                $entity,
                \Jield\Search\Entity\HasSearchInterface::class,
            ));
            $update->addDocument(document: $document);
        }

        $update->addCommit();

        $this->getSolrClient()->update(query: $update);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Exception
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:excessive-parameter-list(1)
    // @mago-expect lint:no-boolean-flag-parameter(2)
    public function updateCollection(
        OutputInterface $output,
        HasSearchInterface $entity,
        bool $clearIndex = false,
        bool $shallow = false,
        int $limit = 50,
        array $criteria = [],
    ): void {
        $output->writeln(messages: '');
        $output->writeln(messages: sprintf('<info>%s</info>', $entity::class));

        if ($clearIndex) {
            $output->writeln(messages: '<error>Index cleared</error>');
            $this->clearIndex();
        }

        if (!$clearIndex) {
            //Always to a check to delete items which are not in the database anymore
            $this->removeDeletedItemsFromIndex(output: $output, entity: $entity);
        }

        $amount = $this->findCount(entity: $entity::class, criteria: $criteria);

        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(1)
        if ($shallow) {
            $amount = min($amount, 1000);
            $output->writeln(messages: sprintf('Shallow update of latest %d items', $amount));
            $limit = 1000; //Shallow updates are done in larger chunks
        } else {
            $output->writeln(messages: sprintf('Full update of %d items of %s', $amount, $entity::class));
        }

        $i = 0;
        $total = 1;
        while ($i < $amount) {
            $update = $this->getSolrClient()->createUpdate();

            $elements = $this->findSliced(entity: $entity::class, limit: $limit, offset: $i, criteria: $criteria);

            /** @mago-var mixed $element */
            foreach ($elements as $element) {
                $document = $this->getSearchDocumentFromEntity(update: $update, entity: Assert::isInstanceOf(
                    $element,
                    \Jield\Search\Entity\HasSearchInterface::class,
                ));

                $update->addDocument(document: $document);
                $output->write(messages: '.');

                if (($total % 100) === 0) {
                    $output->write(messages: ' (' . number_format(num: ($total / $amount) * 100, decimals: 0) . ' %)');
                    $output->writeln(messages: '');
                }

                $total++;
            }

            $update->addCommit();
            $this->updateIndex(output: $output, update: $update);

            //clear the entity manager to prevent piling up entities
            $this->entityManager->clear();

            $i += $limit;
        }

        if ($amount > 0) {
            $output->write(
                messages: ' <info>(' . number_format(num: (($total - 1) / $amount) * 100, decimals: 0) . ' %)</info>',
            );
        }

        $output->writeln(messages: ['', '<comment>Update done</comment>']);
    }

    /**
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
    public function clearIndex(bool $optimize = true): Result
    {
        $update = $this->getSolrClient()->createUpdate();
        $update->addDeleteQuery(query: '*:*');
        $update->addCommit();
        $result = $this->getSolrClient()->update(query: $update);
        if ($optimize) {
            $this->optimizeIndex();
        }

        return $result;
    }

    /**
     * Optimize the current index
     *
     * @see http://wiki.apache.org/solr/SolrPerformanceFactors#Optimization_Considerations
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    public function optimizeIndex(): Result
    {
        $update = $this->getSolrClient()->createUpdate();
        $update->addOptimize(); // No params, just use Solr's default optimization settings

        return $this->getSolrClient()->update(query: $update);
    }

    /**
     * @throws \Exception
     */
    protected function removeDeletedItemsFromIndex(
        OutputInterface $output,
        HasSearchInterface $entity,
        array $criteria = [],
    ): void {
        $databaseIds = $this->findAllIdsFromDatabase(entity: $entity, criteria: $criteria);

        if (count(value: $databaseIds) > 50_000) {
            $output->writeln(messages: sprintf(
                '<error>Too many items to check (%d), please use the update command</error>',
                count(value: $databaseIds),
            ));
            return;
        }

        $searchIndexIds = $this->findAllIdsFromSearchIndex(entity: $entity);

        $toBeDeletedItemsInSearchIndex = array_diff(Value::scalars($searchIndexIds), Value::scalars($databaseIds));

        $update = $this->getSolrClient()->createUpdate();
        foreach ($toBeDeletedItemsInSearchIndex as $remainderId) {
            /**
             * To be able to delete by id we need to reconstruct the resource id, therefore we create a new entity
             */
            $entity = Assert::isInstanceOf(Value::instantiate($entity), HasSearchInterface::class);
            Value::call($entity, 'setId', [$remainderId]);

            $update->addDeleteById(id: $entity->getResourceId());
            $output->writeln(messages: sprintf(
                '<comment>Id %d of %s has been deleted</comment>',
                Value::scalar($remainderId),
                $entity::class,
            ));
        }

        $output->writeln(messages: sprintf('Deleted %d orphaned items', count(value: $toBeDeletedItemsInSearchIndex)));

        $update->addCommit();
        $this->getSolrClient()->update(query: $update);
    }

    /**
     * This function must have an argument so child classes can override it
     *
     * @throws \Exception
     */
    protected function findAllIdsFromSearchIndex(?HasSearchInterface $entity = null): array
    {
        //Get all ids from the index
        $query = $this->getSolrClient()->createSelect();
        $query->setQuery(query: '*:*')->setRows(rows: 1_000_000);
        $ids = [];
        /** @mago-var mixed $document */
        foreach ($this->getSolrClient()->select($query)->getIterator() as $document) {
            /**
             * The document->id is the resource id in SOLR (sprintf('%s-%s', $this->get('underscore_entity_name'), $this->getId());)
             *
             * For this function we are only interested in the "real" id, so we will remove the first part
             */
            $id = explode(
                separator: '-',
                string: Assert::string(Assert::isInstanceOf($document, Document::class)->offsetGet('id')),
            );
            if (($id[1] ?? null) !== null) {
                $ids[] = (int) $id[1];
            }
        }

        return $ids;
    }

    private function findAllIdsFromDatabase(HasSearchInterface $entity, array $criteria): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('e.id');
        $queryBuilder->from(from: $entity::class, alias: 'e');
        if (!!$criteria) {
            /** @mago-var mixed $value */
            foreach ($criteria as $key => $value) {
                $queryBuilder->andWhere(sprintf('e.%s = :%s', $key, $key));
                $queryBuilder->setParameter(key: ':' . $key, value: $value);
            }
        }
        return array_map(
            callback: static fn(array $result) => $result['id'],
            array: $queryBuilder->getQuery()->getArrayResult(),
        );
    }

    /**
     * @param class-string $entity
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function findCount(string $entity, array $criteria): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('COUNT(e.id)');
        $queryBuilder->from(from: $entity, alias: 'e');

        if (!!$criteria) {
            /** @mago-var mixed $value */
            foreach ($criteria as $key => $value) {
                $queryBuilder->andWhere(sprintf('e.%s = :%s', $key, $key));
                $queryBuilder->setParameter(key: ':' . $key, value: $value);
            }
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @param class-string $entity
     * @throws \UnexpectedValueException
     */
    protected function findSliced(string $entity, int $limit, int $offset, array $criteria = []): array
    {
        return $this->entityManager->getRepository(className: $entity)->findBy(
            criteria: Value::stringKeyedArray($criteria),
            orderBy: ['id' => Order::Descending->value],
            limit: $limit,
            offset: $offset,
        );
    }

    /**
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    protected function updateIndex(OutputInterface $output, \Solarium\QueryType\Update\Query\Query $update): void
    {
        try {
            $this->getSolrClient()->update(query: $update);
        } catch (HttpException $e) {
            $responseBody = $e->getBody();

            if (!!($responseBody ?? null)) {
                /** @mago-var mixed $response */
                $response = json_decode(json: $responseBody);
                if (($response->responseHeader ?? null) !== null) {
                    $output->writeln(messages: sprintf(
                        '<error>Solr HTTP response code: %s</error>',
                        Value::scalar(Assert::isInstanceOf(
                            Assert::isInstanceOf($response, stdClass::class)->responseHeader,
                            stdClass::class,
                        )->status),
                    ));
                }
                if (($response->error ?? null) !== null) {
                    $output->writeln(messages: sprintf(
                        '<error>Solr error message: %s</error>',
                        Value::scalar(Assert::isInstanceOf(
                            Assert::isInstanceOf($response, stdClass::class)->error,
                            stdClass::class,
                        )->msg),
                    ));
                }
            }
        }
    }

    /**
     * @throws \Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function syncIndex(OutputInterface $output, HasSearchInterface $entity, array $criteria = []): void
    {
        $output->writeln(messages: '');
        $output->writeln(messages: sprintf('<info>%s</info>', $entity::class));

        $this->addMissingItemsToTheIndex(output: $output, entity: $entity, criteria: $criteria);
        $this->removeDeletedItemsFromIndex(output: $output, entity: $entity, criteria: $criteria);

        $output->writeln(messages: '<comment>Sync done</comment>');
    }

    /**
     * @throws \Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function addMissingItemsToTheIndex(
        OutputInterface $output,
        HasSearchInterface $entity,
        array $criteria = [],
    ): void {
        $databaseIds = $this->findAllIdsFromDatabase(entity: $entity, criteria: $criteria);

        if (count(value: $databaseIds) > 50_000) {
            $output->writeln(messages: sprintf(
                '<error>Too many items to check (%d), please use the update command</error>',
                count(value: $databaseIds),
            ));
            return;
        }

        $searchIndexIds = $this->findAllIdsFromSearchIndex(entity: $entity);

        $toBeAddedItemsInSearchIndex = array_diff(Value::scalars($databaseIds), Value::scalars($searchIndexIds));

        if (count(value: $toBeAddedItemsInSearchIndex) > 200) {
            $output->writeln(messages: sprintf(
                '<error>%d items of %s will be added which is more than the threshold of 200 items, use a sync instead</error>',
                count(value: $toBeAddedItemsInSearchIndex),
                $entity::class,
            ));

            return;
        }

        $update = $this->getSolrClient()->createUpdate();
        foreach ($toBeAddedItemsInSearchIndex as $newId) {
            $entity = $this->entityManager->getRepository(className: $entity::class)->find(id: $newId);
            $document = $this->getSearchDocumentFromEntity(update: $update, entity: Assert::isInstanceOf(
                $entity,
                \Jield\Search\Entity\HasSearchInterface::class,
            ));

            $update->addDocument(document: $document);

            $output->write(messages: '.');
        }

        $update->addCommit();
        $this->updateIndex(output: $output, update: $update);

        $output->writeln(messages: sprintf('Added %d items', count(value: $toBeAddedItemsInSearchIndex)));
    }

    /**
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
    public function deleteDocument(HasSearchInterface $entity, bool $optimize = false): Result
    {
        $update = $this->getSolrClient()->createUpdate();
        $update->addDeleteById(id: $entity->getResourceId());
        $update->addCommit();
        $result = $this->getSolrClient()->update(query: $update);
        if ($optimize) {
            $this->optimizeIndex();
        }

        return $result;
    }

    public function addFilterQuery($key, $value): void
    {
        Assert::notNull($this->query)->addFilterQuery(filterQuery: [
            'key' => $key,
            'query' => Value::stringify($key) . ':(' . Value::stringify($value) . ')',
            'tag' => $key,
        ]);
    }

    /**
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    public function getResultSet(): \Solarium\QueryType\Select\Result\Result
    {
        return $this->getSolrClient()->select($this->getQuery());
    }

    public function getQuery(): Query
    {
        return Assert::isInstanceOf($this->query, \Solarium\QueryType\Select\Query\Query::class);
    }

    protected function setQuery(Query $query): AbstractSearchService
    {
        $this->query = $query;

        //Default add 1000 results
        $this->query->setRows(rows: 1000);

        return $this;
    }

    /**
     * @return FacetField[]
     */
    public function getFacets(): array
    {
        return $this->facets;
    }

    /**
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    public function createFacetFilters(array $facets): void
    {
        $key = 0;

        /** @mago-var mixed $facet */
        foreach ($facets as $field => $facet) {
            //Skip when we have no values
            if (!array_key_exists(key: 'values', array: Assert::isArray($facet)) || !($facet['values'] ?? null)) {
                continue;
            }

            //When we have an AND we need all values
            if (($facet['andOr'] ?? null) !== null && $facet['andOr'] === 'and') {
                /** @mago-var mixed $value */
                foreach (Assert::isIterable($facet['values']) as $value) {
                    $this->addFilterQueryFromFacet(
                        key: $key++,
                        field: Assert::string($field),
                        value: Value::arrayOrIntOrString($value),
                        exclude: ($facet['yesNo'] ?? null) !== null && $facet['yesNo'] === 'no',
                        and: true,
                    );
                }
            }

            //An or is just to put all values in the result
            if (($facet['andOr'] ?? null) === null) {
                $this->addFilterQueryFromFacet(
                    key: $key++,
                    field: Assert::string($field),
                    value: Value::arrayOrIntOrString($facet['values']),
                    exclude: ($facet['yesNo'] ?? null) !== null && $facet['yesNo'] === 'no',
                );
            }
        }
    }

    /**
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(2)
    private function addFilterQueryFromFacet(
        int $key,
        string $field,
        string|array|int $value,
        bool $exclude = false,
        bool $and = false,
    ): void {
        if (!$this->hasFacet(fieldName: $field)) {
            return;
        }

        $facetField = $this->getFacet(fieldName: $field);

        switch (true) {
            case $facetField->isCheckboxMin():
                //Take the last element of the array
                /** @mago-var mixed $value */
                $value = $value[0] ?? '';
                $query = sprintf('%s%s:[%d TO *]', $exclude ? '-' : '', $field, Value::scalar($value));
                break;
            case !$facetField->isSlider():
                if (is_string(value: $value)) {
                    $value = sprintf('"%s"', $value);
                }

                if (is_array(value: $value)) {
                    $value = array_map(callback: static fn(string $value) => sprintf('"%s"', $value), array: $value);

                    $value = implode(separator: ' ', array: $value);
                }

                $query = sprintf('%s%s:(%s)', $exclude ? '-' : '', $field, $value);
                break;
            default:
                $values = explode(separator: ',', string: Assert::string($value));
                $min = $values[0];
                $max = $values[1] ?? '';

                //@todo; this fields a bit sloppy
                // These alternatives are mutually exclusive; the following code consumes the selected state.
                // @mago-expect lint:no-else-clause(1)
                if (str_contains(haystack: $field, needle: 'array_int')) {
                    $minField = str_replace(search: 'array_int', replace: 'min', subject: $field);
                    $maxField = str_replace(search: 'array_int', replace: 'max', subject: $field);

                    $query = sprintf('%s:[* TO %d] AND %s:[%d TO *]', $minField, $min, $maxField, $max);
                } else {
                    $query = sprintf('%s:[%d TO %d]', $field, $min, $max);
                }
                break;
        }

        Assert::notNull($this->query)
            ->createFilterQuery(options: $key . $field)
            ->setQuery(query: $query)
            ->getLocalParameters()
            ->addTags(tags: [$facetField->getField()]);

        if ($and) {
            $facet = Assert::notNull($this->facetSet)->getFacet(key: $this->getFacet(fieldName: $field)->getField());
            Assert::isInstanceOf($facet, Field::class)->setMinCount(1);
        }
    }

    public function getFacet(string $fieldName): FacetField
    {
        return $this->facets[$fieldName];
    }

    public function hasFacet(string $fieldName): bool
    {
        return ($this->facets[$fieldName] ?? null) !== null;
    }

    /**
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
    public function testIndex(OutputInterface $output, HasSearchInterface $entity, bool $clearIndex): void
    {
        $output->writeln(messages: '');
        $output->writeln(messages: sprintf('<info>%s</info>', $entity::class));

        $coreAdminQuery = $this->getSolrClient()->createCoreAdmin();

        if ($clearIndex) {
            $this->clearIndex();
            $output->writeln(messages: sprintf('<error>Index of core %s cleared</error>', $this->connection));
        }

        // use the CoreAdmin query to build a Reload action
        $statusAction = $coreAdminQuery->createReload();
        $statusAction->setCore(core: $this->connection);
        $coreAdminQuery->setAction(action: $statusAction);

        $this->getSolrClient()->coreAdmin(query: $coreAdminQuery);

        $output->writeln(messages: sprintf('Reload of core %s done', $this->connection));

        //Grab an array with all ids from the database
        $qb = $this->entityManager->createQueryBuilder();
        $result = Assert::isArray(
            $qb
                ->select('e')
                ->from(from: $entity::class, alias: 'e')
                ->setMaxResults(maxResults: 1)
                ->getQuery()
                ->getResult(),
        );

        $update = $this->getSolrClient()->createUpdate();

        $requireDelete = false;

        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(1)
        if (count($result) === 0) {
            $entity = Assert::isInstanceOf(Value::instantiate($entity), HasSearchInterface::class);

            $output->writeln(messages: sprintf('<comment>Test with empty object %s</comment>', $entity::class));

            $requireDelete = true;
        } else {
            /** @mago-var mixed $entity */
            $entity = $result[0];
        }

        $document = $this->getSearchDocumentFromEntity(update: $update, entity: Assert::isInstanceOf(
            $entity,
            \Jield\Search\Entity\HasSearchInterface::class,
        ));
        $update->addDocument(document: $document);
        $update->addCommit();

        try {
            $this->getSolrClient()->update(query: $update);
            $output->writeln(messages: '<comment>Test successful</comment>');

            if ($requireDelete) {
                $this->deleteDocument(entity: Assert::isInstanceOf(
                    $entity,
                    \Jield\Search\Entity\HasSearchInterface::class,
                ));
                $output->writeln(messages: '<comment>Test document deleted</comment>');
            }
        } catch (HttpException $e) {
            $responseBody = $e->getBody();
            /** @mago-var mixed $response */
            $response = json_decode(json: Assert::string($responseBody));

            $output->writeln(messages: sprintf(
                '<error>Error updating %s: %s</error>',
                Value::scalar($entity::class),
                Value::scalar(Assert::isInstanceOf(
                    Assert::isInstanceOf($response, stdClass::class)->error,
                    stdClass::class,
                )->msg),
            ));
        }
    }

    /**
     * @throws \Solarium\Exception\OutOfBoundsException
     */
    protected function createFacet(FacetField $facetField): void
    {
        $facet = Assert::notNull($this->facetSet)->createFacetField(options: $facetField->getField());
        $facet
            ->setField(field: $facetField->getField())
            ->setMinCount(minCount: $facetField->getMinCount())
            ->setLimit(limit: $facetField->getLimit())
            ->setSort(sort: $facetField->getSort());

        if (!$facetField->getHasAndOr()) {
            $facet->getLocalParameters()->setExclude($facetField->getField());
        }

        $this->facets[$facetField->getField()] = $facetField; //Name is used as index
    }
}
