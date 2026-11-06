<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Filter;

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
        return new BaseFilter($this->targetOrOptions ?? [], $mover)->filter($value);
    }
}
