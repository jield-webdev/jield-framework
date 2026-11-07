<?php

declare(strict_types=1);

namespace Jield\Export\Service;

use AzureOSS\Storage\Blob\BlobRestProxy;
use codename\parquet\data\Schema;
use codename\parquet\ParquetWriter;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Jield\Export\Columns\AbstractEntityColumns;
use Jield\Export\Columns\ColumnsHelperInterface;
use Jield\Export\Entity\StorageLocationInterface;
use Jield\Export\Enum\ExportFileTypeEnum;
use Jield\Export\Json\AbstractEntityJson;
use Jield\Export\Options\ModuleOptions;
use Jield\Export\ValueObject\Column;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
class ConsoleService
{
    private array $entities = [];
    /**
     * @var StorageLocationInterface[]
     */
    private ?array $storageLocations = null;

    protected BlobRestProxy $blobClient;

    protected StorageLocationServiceInterface $storageLocationService;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly EntityManager $entityManager,
        private readonly ModuleOptions $moduleOptions,
    ) {
        //Do an init check
        /** @mago-var mixed $entityColumnsName */
        foreach ($this->moduleOptions->getEntities() as $key => $entityColumnsName) {
            $this->entities[$key] = $entityColumnsName;
        }
    }

    /**
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function generateDocumentation(OutputInterface $output): void
    {
        $tempImageFile = __DIR__ . '/../../../../../data/documentation.md';
        $handle = fopen(filename: $tempImageFile, mode: 'wb');
        $entities = [];

        /** @mago-var mixed $entityInformation */
        foreach ($this->moduleOptions->getEntities() as $entityInformation) {
            if (!array_key_exists('columns', Assert::isArray($entityInformation))) {
                continue;
            }

            $this->collectDocumentationEntities(
                entityColumnsName: Assert::string($entityInformation['columns']),
                entities: $entities,
            );
        }

        uasort($entities, static fn(AbstractEntityColumns $left, AbstractEntityColumns $right): int => strcasecmp(
            $left->getName(),
            $right->getName(),
        ));

        /** @mago-var mixed $entity */
        foreach ($entities as $entity) {
            $output->writeln(messages: sprintf(
                '<info>Writing MarkDown file for %s</info>',
                Value::scalar(Value::call($entity, 'getName')),
            ));

            $this->writeMarkdownContent(
                createColumnsClass: Assert::isInstanceOf($entity, \Jield\Export\Columns\AbstractEntityColumns::class),
                handle: $handle,
            );
        }

        fclose(stream: $handle);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    private function collectDocumentationEntities(string $entityColumnsName, array &$entities): void
    {
        if (array_key_exists($entityColumnsName, $entities)) {
            return;
        }

        $createColumnsClass = $this->getEntityColumnsClass(entityColumnsName: $entityColumnsName);
        $entities[$entityColumnsName] = $createColumnsClass;

        /** @mago-var mixed $dependency */
        foreach ($createColumnsClass->getDependencies() as $dependency) {
            $this->collectDocumentationEntities(entityColumnsName: Assert::string($dependency), entities: $entities);
        }
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    private function getEntityColumnsClass(string $entityColumnsName): AbstractEntityColumns
    {
        /** @var AbstractEntityColumns $createColumnsClass */
        // Keep the local variable as the target of its generic type annotation.
        // @mago-expect lint:inline-variable-return(1)
        $createColumnsClass = $this->container->has($entityColumnsName)
            ? $this->container->get($entityColumnsName)
            : Value::instantiate($entityColumnsName, [Service::get($this->container, EntityManager::class)]);

        return $createColumnsClass;
    }

    private function writeMarkdownContent(AbstractEntityColumns $createColumnsClass, mixed $handle): void
    {
        $markDown = <<<MARKDOWN
            ## {$createColumnsClass->getName()}

            {$createColumnsClass->getDescription()}

            | Column | Type | Nullable | Description |
            |--------|------|----------|-------------|

            MARKDOWN;

        foreach ($createColumnsClass->getColumns() as $column) {
            $markDown .= <<<MARKDOWN
                |{$column->getColumnName()} | {$column->getType()} | {$column->isNullableText()} | {$column->getDescription()}|

                MARKDOWN;
        }

        fwrite(stream: Value::resource($handle), data: $markDown);
    }

    /**
     * @param StorageLocationInterface[] $storageLocations
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \JsonException
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \ReflectionException
     * @throws \UnexpectedValueException
     */
    public function sendEntity(OutputInterface $output, string $entity, array $storageLocations): void
    {
        if ($entity === 'all') {
            /** @mago-var mixed $entityInformation */
            foreach ($this->entities as $entityInformation) {
                foreach ($storageLocations as $storageLocation) {
                    $columnKey = $storageLocation->getExportFileType()->getColumnKey();

                    if (array_key_exists($columnKey, Assert::isArray($entityInformation))) {
                        $this->handleEntity(
                            storageLocation: $storageLocation,
                            columnOrJsonClass: Assert::string($entityInformation[$columnKey]),
                            output: $output,
                        );
                    }
                }
            }

            return;
        }

        if (($this->entities[$entity] ?? null) === null) {
            $output->writeln(messages: sprintf('<error>Entity %s not found</error>', $entity));

            return;
        }

        $output->writeln(messages: sprintf('<info>Updating entity %s</info>', $entity));

        foreach ($storageLocations as $storageLocation) {
            $columnKey = $storageLocation->getExportFileType()->getColumnKey();

            $this->handleEntity(
                storageLocation: $storageLocation,
                columnOrJsonClass: Assert::string(Value::accessible($this->entities[$entity])[$columnKey]),
                output: $output,
            );
        }
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \JsonException
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \ReflectionException
     * @throws InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function handleEntity(
        StorageLocationInterface $storageLocation,
        string $columnOrJsonClass,
        OutputInterface $output,
    ): void {
        $output->writeLn(messages: sprintf(
            '<comment>Sending %s to %s</comment>',
            $columnOrJsonClass,
            $storageLocation->getName(),
        ));

        $startTime = microtime(as_float: true);

        switch ($storageLocation->getExportFileType()) {
            case ExportFileTypeEnum::PARQUET:
                //Try to grab the entity from the container, otherwise instantiate it
                /** @var AbstractEntityColumns $createColumnsOrJsonClass */
                /** @var AbstractEntityColumns $createColumnsOrJsonClass */
                $createColumnsOrJsonClass = $this->container->has($columnOrJsonClass)
                    ? $this->container->get($columnOrJsonClass)
                    : Value::instantiate($columnOrJsonClass, [Service::get($this->container, EntityManager::class)]);
                $columns = $createColumnsOrJsonClass->getColumns();
                //Fetch the columns so we have to do this once
                $this->createParquetAndCreateBlob(
                    storageLocation: $storageLocation,
                    columnsHelper: $createColumnsOrJsonClass,
                    columns: $columns,
                );
                break;
            case ExportFileTypeEnum::EXCEL:
            case ExportFileTypeEnum::CSV:
                //Try to grab the entity from the container, otherwise instantiate it
                /** @var AbstractEntityColumns $createColumnsOrJsonClass */
                /** @var AbstractEntityColumns $createColumnsOrJsonClass */
                $createColumnsOrJsonClass = $this->container->has($columnOrJsonClass)
                    ? $this->container->get($columnOrJsonClass)
                    : Value::instantiate($columnOrJsonClass, [Service::get($this->container, EntityManager::class)]);
                $columns = $createColumnsOrJsonClass->getColumns();
                $this->createExcel(
                    storageLocation: $storageLocation,
                    columnsHelper: $createColumnsOrJsonClass,
                    columns: $columns,
                );
                break;
            case ExportFileTypeEnum::JSON:
                /** @var AbstractEntityJson $createColumnsOrJsonClass */
                $createColumnsOrJsonClass = Value::instantiate($columnOrJsonClass, [$this->container]);

                $this->createJson(storageLocation: $storageLocation, jsonHelper: $createColumnsOrJsonClass);
                break;
            default:
                throw new InvalidArgumentException(message: sprintf(
                    'Storage location %s is not supported',
                    $storageLocation->getName(),
                ));
        }

        $output->writeLn(messages: sprintf('Finished in %04f seconds', microtime(as_float: true) - $startTime));
        $output->writeLn(messages: sprintf(
            'Current memory consumption: %d MiB',
            (memory_get_usage(true) / 1024) / 1024,
        ));

        //Check if the entity has dependencies
        /** @mago-var mixed $dependency */
        foreach ($createColumnsOrJsonClass->getDependencies() as $dependency) {
            $this->handleEntity(
                storageLocation: $storageLocation,
                columnOrJsonClass: Assert::string($dependency),
                output: $output,
            );
        }
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws InvalidArgumentException
     */
    private function createParquetAndCreateBlob(
        StorageLocationInterface $storageLocation,
        ColumnsHelperInterface $columnsHelper,
        array $columns,
    ): void {
        if (!$storageLocation->getExportFileType()->isParquet()) {
            throw new InvalidArgumentException('Storage location is not an Parquet file type');
        }

        $fields = array_map(callback: static fn(Column $column) => $column
            ->toParquetColumn()
            ->getField(), array: $columns);

        $schema = new Schema(fields: $fields);

        $fileName = $this->generateTempFileName(storageLocation: $storageLocation, name: $columnsHelper->getName());
        $fileStream = fopen(filename: $fileName, mode: 'wb+');
        $parquetWriter = new ParquetWriter(schema: $schema, output: $fileStream);

        $groupWriter = $parquetWriter->CreateRowGroup();
        /** @var Column $column */
        foreach ($columns as $column) {
            $groupWriter->WriteColumn(column: $column->toParquetColumn());
        }

        $groupWriter->finish();
        $parquetWriter->finish();

        $this->getBlobClient()->createBlockBlob(
            container: $storageLocation->getContainer(),
            blob: $this->generateBlobName(storageLocation: $storageLocation, name: $columnsHelper->getName()),
            content: Assert::string(file_get_contents(filename: $fileName)),
        );
    }

    /**
     * @throws \JsonException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     * @throws \UnexpectedValueException
     * @throws InvalidArgumentException
     */
    private function createJson(StorageLocationInterface $storageLocation, AbstractEntityJson $jsonHelper): void
    {
        if (!$storageLocation->getExportFileType()->isJson()) {
            throw new InvalidArgumentException('Storage location is not an Json file type');
        }

        $jsonData = $jsonHelper->getJsonData();

        //Save the JSON data to a temporary file

        //        $fileName = $this->generateTempFileName($storageLocation, name: $jsonHelper->getName());

        $this->getBlobClient()->createBlockBlob(
            container: $storageLocation->getContainer(),
            blob: $this->generateBlobName(storageLocation: $storageLocation, name: $jsonHelper->getName()),
            content: $jsonData,
        );
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws InvalidArgumentException
     */
    private function createExcel(
        StorageLocationInterface $storageLocation,
        ColumnsHelperInterface $columnsHelper,
        array $columns,
    ): void {
        if (!$storageLocation->getExportFileType()->isSpreadsheet()) {
            throw new InvalidArgumentException('Storage location is not an Excel file type');
        }

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $worksheet->setTitle(title: substr(string: $columnsHelper->getName(), offset: 0, length: 30)); //Excel has a limit of 31 characters
        $worksheet->getPageSetup()->setPaperSize(paperSize: PageSetup::PAPERSIZE_A4);
        $worksheet->getPageSetup()->setFitToWidth(value: 1);
        $worksheet->getPageSetup()->setFitToHeight(fitToHeight: 0);

        $excelColumnIndex = 1;

        /** @var Column $column */
        foreach ($columns as $column) {
            $excelColumn = Coordinate::stringFromColumnIndex($excelColumnIndex++);
            $worksheet->setCellValue(coordinate: $excelColumn . 1, value: $column->toParquetColumn()->getField()->name);

            /** @mago-var mixed $data */
            foreach ($column->toParquetColumn()->getData() as $row => $data) {
                //When we accept a string, we explicitly set the type to string to avoid issues with formulas
                if ($column->getType() === Column::TYPE_STRING) {
                    $worksheet->setCellValueExplicit(
                        coordinate: $excelColumn . (Assert::integer($row) + 2),
                        value: $data,
                        dataType: DataType::TYPE_STRING,
                    );
                    continue;
                }
                $worksheet->setCellValue(coordinate: $excelColumn . (Assert::integer($row) + 2), value: $data);
            }

            //Go to the next Excel Column
        }

        $fileName = $this->generateTempFileName($storageLocation, name: $columnsHelper->getName());

        /** @var Xlsx $writer */
        $writer = IOFactory::createWriter(
            spreadsheet: $spreadsheet,
            writerType: $storageLocation->getExportFileType() === ExportFileTypeEnum::EXCEL
                ? IOFactory::WRITER_XLSX
                : IOFactory::READER_CSV,
        );
        $writer->save(filename: $fileName);

        $this->getBlobClient()->createBlockBlob(
            container: $storageLocation->getContainer(),
            blob: $this->generateBlobName(storageLocation: $storageLocation, name: $columnsHelper->getName()),
            content: Assert::string(file_get_contents(filename: $fileName)),
        );

        $spreadsheet->disconnectWorksheets();
        $spreadsheet->garbageCollect();
        unset($spreadsheet);
        gc_collect_cycles();
    }

    private function generateBlobName(StorageLocationInterface $storageLocation, string $name): string
    {
        return sprintf(
            '%s/%s.%s',
            $storageLocation->getFolder(),
            $name,
            $storageLocation->getExportFileType()->parseExtension(),
        );
    }

    private function generateTempFileName(StorageLocationInterface $storageLocation, string $name): string
    {
        return sprintf('%s/%s.%s', sys_get_temp_dir(), $name, $storageLocation->getExportFileType()->parseExtension());
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws InvalidArgumentException
     */
    private function getBlobClient(): \AzureOSS\Storage\Blob\BlobRestProxy
    {
        if (($this->blobClient ?? null) === null) {
            //Grab the service from the service container
            if (!$this->container->has(StorageLocationServiceInterface::class)) {
                throw new InvalidArgumentException('StorageLocationServiceInterface not found in container');
            }

            $this->storageLocationService = Service::get($this->container, StorageLocationServiceInterface::class);

            $this->blobClient = $this->storageLocationService->getBlobService();
        }

        return $this->blobClient;
    }

    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * @return array<array-key, StorageLocationInterface>
     * @throws \UnexpectedValueException
     * @throws \ReflectionException
     */
    public function getStorageLocationsForExport(): array
    {
        if (null === $this->storageLocations) {
            //Find the entity which holds the storage location
            $storageLocationEntity = $this->moduleOptions->getStorageLocationEntity();
            Assert::isAOf($storageLocationEntity, StorageLocationInterface::class);

            //This entity has to implement the StorageLocationInterface
            Assert::implementsInterface(
                value: Value::instantiate($storageLocationEntity),
                interface: StorageLocationInterface::class,
            );

            $storageLocations = $this->entityManager->getRepository(
                $storageLocationEntity,
            )->findBy(criteria: [], orderBy: ['name' => Order::Ascending->value]);

            //Based on the getType() of the storage location, we filter the storage locations on the export file type
            //This does not have to be a column but can also be derived from a local purpose or ID
            $this->storageLocations = array_filter(array: $storageLocations, callback: static fn(StorageLocationInterface $storageLocation) => $storageLocation
                ->getType()
                ->isExport());
        }

        return $this->storageLocations;
    }
}
