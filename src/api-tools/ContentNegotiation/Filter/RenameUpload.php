<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Filter;

use Jield\Stdlib\Value;
use Laminas\Filter\Exception\RuntimeException;
use Laminas\Filter\File\MoveUploadedFileInterface;
use Laminas\Filter\File\RenameUpload as BaseFilter;
use Laminas\Filter\FilterInterface;
use Laminas\Stdlib\RequestInterface;

/**
 * @implements \Laminas\Filter\FilterInterface<mixed>
 */
class RenameUpload implements FilterInterface
{
    private ?RequestInterface $request = null;

    public function __construct(
        private readonly ?array $targetOrOptions = null,
    ) {}

    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    public function __invoke(mixed $value): mixed
    {
        return $this->filter($value);
    }

    public function filter(mixed $value): mixed
    {
        $mover = null;
        if (
            $this->request !== null
            && method_exists($this->request, 'getMethod')
            && in_array($this->request->getMethod(), ['PUT', 'PATCH'], true)
        ) {
            $mover = new class implements MoveUploadedFileInterface {
                public function moveUploadedFile(string $sourceFile, string $targetFile): void
                {
                    if (!rename($sourceFile, $targetFile)) {
                        throw new RuntimeException('Could not move the uploaded file.');
                    }
                }
            };
        }
        $options = $this->targetOrOptions ?? [];
        return new BaseFilter([
            'target' => Value::nonEmptyString($options['target'] ?? '*'),
            'use_upload_name' => Value::bool($options['use_upload_name'] ?? false),
            'use_upload_extension' => Value::bool($options['use_upload_extension'] ?? false),
            'overwrite' => Value::bool($options['overwrite'] ?? false),
            'randomize' => Value::bool($options['randomize'] ?? false),
        ], $mover)->filter($value);
    }
}
