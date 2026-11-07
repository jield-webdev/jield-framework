<?php

declare(strict_types=1);

namespace JieldTest\ApiTools;

use ArrayObject;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\ApiTools\ContentNegotiation\MultipartContentParser;
use Jield\ApiTools\Hal\EntityHydratorManager;
use Jield\ApiTools\Hal\Extractor\EntityExtractor;
use Jield\ApiTools\Hal\Link\Link;
use Jield\ApiTools\Hal\Link\LinkCollection;
use Jield\ApiTools\Hal\Metadata\MetadataMap;
use Jield\ApiTools\Hal\Plugin\Hal;
use Jield\ApiTools\Rest\Resource;
use Jield\ApiTools\Rest\ResourceEvent;
use Laminas\Http\Header\ContentType;
use Laminas\Http\Request;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

final class CompatibilityTest extends TestCase
{
    public function testDashedAndUnderscoredNegotiationOptionsAreMerged(): void
    {
        $options = new ContentNegotiationOptions([
            'accept-whitelist' => ['api' => ['application/json']],
            'accept_whitelist' => ['api' => ['application/xml']],
            'x-http-method-override-enabled' => true,
        ]);

        self::assertSame(['api' => ['application/json', 'application/xml']], $options->getAcceptWhitelist());
        self::assertTrue($options->getXHttpMethodOverrideEnabled());
    }

    public function testMultipartUploadKeepsFieldsAndDetectsTheFileType(): void
    {
        $request = new Request();
        $request->setMethod('PUT');
        $request->setContent(
            "--mago-test\r\nContent-Disposition: form-data; name=\"message\"\r\n\r\nhello\r\n"
            . "--mago-test\r\nContent-Disposition: form-data; name=\"upload\"; filename=\"note.txt\"\r\n"
            . "Content-Type: application/octet-stream\r\n\r\nA text file\r\n--mago-test--\r\n",
        );
        $parser = new MultipartContentParser(
            ContentType::fromString('Content-Type: multipart/form-data; boundary=mago-test'),
            $request,
        );

        self::assertSame(['message' => 'hello'], $parser->parse());
        $file = $request->getFiles()->get('upload');
        try {
            self::assertSame(UPLOAD_ERR_OK, $file['error']);
            self::assertSame('text/plain', $file['type']);
            self::assertSame('A text file', file_get_contents($file['tmp_name']));
            self::assertSame(11, $file['size']);
        } finally {
            unlink($file['tmp_name']);
        }
    }

    public function testHalCreatesItsHydratorManagerAndCachesExtractedEntities(): void
    {
        $hydrators = new HydratorPluginManager(new ServiceManager());
        $hal = new Hal($hydrators);
        self::assertSame($hal->getEntityHydratorManager(), $hal->getEntityHydratorManager());

        $extractor = new EntityExtractor(new EntityHydratorManager($hydrators, new MetadataMap()));
        $entity = new class implements \JsonSerializable {
            public int $calls = 0;

            public function jsonSerialize(): array
            {
                return ['calls' => ++$this->calls];
            }
        };
        self::assertSame(['calls' => 1], $extractor->extract($entity));
        self::assertSame(['calls' => 1], $extractor->extract($entity));
        self::assertSame(1, $entity->calls);
    }

    public function testMetadataCanBeInheritedAndMissingMetadataReturnsFalse(): void
    {
        $map = new MetadataMap([\stdClass::class => []]);
        self::assertTrue($map->has(new class extends \stdClass {
        }));
        self::assertFalse($map->has(new ArrayObject()));
        self::assertFalse($map->get(new ArrayObject()));
    }

    public function testLinkCollectionsRetainDistinctLinksWithTheSameRelation(): void
    {
        $first = new Link('item')->setUrl('https://example.test/one');
        $second = new Link('item')->setUrl('https://example.test/two');
        $links = new LinkCollection();
        $links->idempotentAdd($first);
        $links->idempotentAdd($first);
        self::assertSame($first, $links->get('item'));
        $links->idempotentAdd($second);
        self::assertSame([$first, $second], $links->get('item'));
        self::assertNull($links->get('missing'));
    }

    public function testResourceEventsExtractRequestsFromBothArrayFormats(): void
    {
        $request = new Request();
        foreach ([['request' => $request, 'id' => 7], new ArrayObject(['request' => $request, 'id' => 7])] as $params) {
            $event = new ResourceEvent();
            $event->setParams($params);
            self::assertSame($request, $event->getRequest());
            self::assertSame(7, $event->getParam('id'));
            self::assertNull($event->getParam('request'));
        }
    }

    public function testFetchAllCanBeCalledWithoutArguments(): void
    {
        $resource = new Resource();
        $resource->getEventManager()->attach('fetchAll', static fn (): array => ['item']);
        self::assertSame(['item'], $resource->fetchAll());
        self::assertSame(0, new \ReflectionMethod($resource, 'fetchAll')->getNumberOfRequiredParameters());
    }
}
