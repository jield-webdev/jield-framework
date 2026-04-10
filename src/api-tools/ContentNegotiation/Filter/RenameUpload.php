<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Filter;

use Laminas\Filter\Exception\RuntimeException as FilterRuntimeException;
use Laminas\Filter\File\RenameUpload as BaseFilter;
use Laminas\Stdlib\ErrorHandler;
use Laminas\Stdlib\RequestInterface;

use Override;
use function method_exists;
use function rename;
use function sprintf;

class RenameUpload extends BaseFilter
{
    /** @var RequestInterface */
    protected RequestInterface $request;

    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Override moveUploadedFile
     *
     * If the request is not HTTP, or not a PUT or PATCH request, delegates to
     * the parent functionality.
     *
     * Otherwise, does a `rename()` operation, and returns the status of the
     * operation.
     *
     * @param string $sourceFile
     * @param string $targetFile
     * @return bool
     * @throws FilterRuntimeException In the event of a warning.
     */
    #[Override]
    protected function moveUploadedFile(string $sourceFile, string $targetFile): bool
    {
        $isPut = $this->request !== null
            && method_exists(object_or_class: $this->request, method: 'isPut')
            && $this->request->isPut();
        $isPatch = $this->request !== null
            && method_exists(object_or_class: $this->request, method: 'isPatch')
            && $this->request->isPatch();

        if (null === $this->request || (! $isPut && ! $isPatch)) {
            return parent::moveUploadedFile(sourceFile: $sourceFile, targetFile: $targetFile);
        }

        ErrorHandler::start();
        $result           = rename(from: $sourceFile, to: $targetFile);
        $warningException = ErrorHandler::stop();

        if (false === $result || null !== $warningException) {
            throw new FilterRuntimeException(
                message: sprintf('File "%s" could not be renamed. An error occurred while processing the file.', $sourceFile),
                code: 0,
                previous: $warningException
            );
        }

        return true;
    }
}
