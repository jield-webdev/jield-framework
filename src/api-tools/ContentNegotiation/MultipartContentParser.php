<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use finfo;
use Laminas\Http\Header\ContentType as ContentTypeHeader;
use Laminas\Http\Request as HttpRequest;
use Laminas\Stdlib\Parameters;
use Webmozart\Assert\Assert;

use function fclose;
use function fgets;
use function filesize;
use function fopen;
use function function_exists;
use function fwrite;
use function ini_get;
use function is_dir;
use function preg_match;
use function rawurlencode;
use function rewind;
use function rtrim;
use function sprintf;
use function strtoupper;
use function sys_get_temp_dir;
use function tempnam;

use const FILEINFO_MIME_TYPE;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;

class MultipartContentParser
{
    /**
     * MIME multipart boundary
     */
    protected string $boundary;

    /**
     * @throws Exception\InvalidMultipartContentException If unable to detect MIME boundary.
     */
    public function __construct(
        ContentTypeHeader $contentType,
        protected HttpRequest $request,
    ) {
        $matches = [];
        if (!preg_match(
            pattern: '/boundary=(?P<boundary>[^\s]+)/',
            subject: $contentType->getFieldValue(),
            matches: $matches,
        )) {
            throw new Exception\InvalidMultipartContentException();
        }

        $this->boundary = $matches['boundary'];
    }

    /**
     * Retrieve the upload temporary directory
     *
     * Queries for the INI upload_tmp_dir setting first, and returns that
     * value if set and a valid directory; otherwise, returns the
     * system_temp_dir().
     */
    public static function getUploadTempDir(): string
    {
        $tmpDir = ini_get(option: 'upload_tmp_dir');
        if (!($tmpDir === '' || $tmpDir === '0' || $tmpDir === false) && is_dir(filename: $tmpDir)) {
            return $tmpDir;
        }

        return sys_get_temp_dir();
    }

    /**
     * Parse the incoming request body
     *
     * Returns any discovered data parameters.
     *
     * @throws \RuntimeException
     */
    public function parse(): array
    {
        if ($this->request instanceof Request) {
            return $this->parseFromStream(stream: $this->request->getContentAsStream());
        }

        $stream = fopen(filename: 'php://temp', mode: 'r+');
        fwrite(stream: $stream, data: (string) $this->request->getContent());
        rewind(stream: $stream);

        return $this->parseFromStream(stream: $stream);
    }

    /**
     * Parse upload content from a content stream
     *
     * @param resource $stream
     * @throws Exception\InvalidMultipartContentException
     */
    protected function parseFromStream($stream): array
    {
        $matches = [];
        $buffer = '';
        $data = new Parameters();
        $files = new Parameters();
        $partInProgress = false;
        $inHeader = false;
        $headers = [];
        $header = false;
        $name = false;
        $content = '';
        $file = ['error' => UPLOAD_ERR_OK];
        $filename = false;
        $mimeType = false;
        $tmpFile = false;
        $lastLine = '';

        $partBoundaryPatternStart = '/^--' . $this->boundary . '(--)?/';
        $partBoundaryPatternEnd = '/^--' . $this->boundary . '--$/';

        while (false !== ($line = fgets(stream: $stream))) {
            $trimmedLine = rtrim(string: $line);

            if (preg_match(pattern: $partBoundaryPatternStart, subject: $trimmedLine)) {
                if ($partInProgress) {
                    // Time to handle the data we've already parsed!
                    // Data
                    if (!$filename) {
                        $content = rtrim(string: $content, characters: "\r\n");
                        $buffer .= sprintf('%s=%s&', $name, rawurlencode(string: $content));
                    }

                    // File (successful upload so far)
                    if ($filename && $tmpFile && isset($file['tmp_name'])) {
                        // Write the last line, stripping the EOL characters
                        if (
                            false === fwrite(stream: $tmpFile, data: rtrim(
                                string: Assert::string($lastLine),
                                characters: "\r\n",
                            ))
                        ) {
                            // Ooops! error writing the very last line!
                            $file['error'] = UPLOAD_ERR_CANT_WRITE;
                            fclose(stream: $tmpFile);
                        } else {
                            // Success! Let's try and guess the MIME type based on the file written
                            fclose(stream: $tmpFile);

                            if ($mimeType === 'application/octet-stream' && function_exists(function: 'finfo_open')) {
                                $finfo = new finfo(FILEINFO_MIME_TYPE);
                                $type = $finfo->file(filename: Assert::string($file['tmp_name']));
                                if (false !== $type) {
                                    $file['type'] = $type;
                                }
                            }

                            // Finally, set the filesize
                            $file['size'] = filesize(filename: Assert::string($file['tmp_name']));
                        }
                    }

                    if ($filename) {
                        // At this point, we can add the file entry, regardless of error condition
                        $files->set(name: Assert::string($name), value: $file);
                    }
                }

                // Is this a boundary end? If so, we're done
                if (preg_match(pattern: $partBoundaryPatternEnd, subject: $trimmedLine)) {
                    // Met the "end" boundary; time to stop!
                    break;
                }

                // New part to parse!
                $partInProgress = true;
                $inHeader = true;
                $headers = [];
                $header = '';

                continue;
            }

            if (!$partInProgress) {
                // We're not inside a part, so do nothing.
                continue;
            }

            if ($inHeader) {
                if (preg_match(pattern: '/^\s*$/s', subject: $line)) {
                    // Headers are done; cleanup
                    $inHeader = false;
                    $content = '';
                    $file = ['error' => UPLOAD_ERR_OK];
                    $tmpFile = false;
                    $lastLine = null;

                    // Parse headers
                    $name = $this->getNameFromHeaders(headers: $headers);

                    if (!$name) {
                        throw new Exception\InvalidMultipartContentException(
                            message: 'Missing Content-Disposition header, or Content-Disposition header does not contain a "name" field',
                        );
                    }

                    $filename = $this->getFilenameFromHeaders(headers: $headers);
                    $mimeType = $this->getMimeTypeFromHeaders(headers: $headers);
                    continue;
                }

                if (preg_match(
                    pattern: '/^(?P<header>[a-z]+[a-z0-9_-]+):\s*(?P<value>.*)$/i',
                    subject: $trimmedLine,
                    matches: $matches,
                )) {
                    $header = strtoupper(string: $matches['header']);
                    $headers[$header] = $matches['value'];

                    continue;
                }

                if (!$header) {
                    throw new Exception\InvalidMultipartContentException(
                        message: 'Malformed or missing MIME part header for multipart content',
                    );
                }

                $headers[$header] .= $trimmedLine;
                continue;
            }

            // In the body content...

            // Data only; aggregate.
            if (!$filename) {
                $content .= $line;
                continue;
            }

            // If we've had an error already with the upload, continue parsing
            // to the end of the MIME part
            if ($file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Create a temporary file handle if we haven't already
            if (!$tmpFile) {
                // Sets the file entry
                $file['name'] = $filename;
                $file['type'] = $mimeType;

                $tmpDir = static::getUploadTempDir();
                if ($tmpDir === '' || $tmpDir === '0') {
                    // Cannot ascertain temporary directory; this is an error
                    $file['error'] = UPLOAD_ERR_NO_TMP_DIR;
                    continue;
                }

                $file['tmp_name'] = tempnam(directory: $tmpDir, prefix: 'laminasc');
                $tmpFile = fopen(filename: Assert::string($file['tmp_name']), mode: 'wb');
                if (false === $tmpFile) {
                    // Cannot open the temporary file for writing; this is an error
                    $file['error'] = UPLOAD_ERR_CANT_WRITE;
                    continue;
                }
            }

            // Off-by-one operation. Last line must be trimmed, so we'll write
            // the lines one iteration behind.
            if (empty($lastLine)) {
                $lastLine = $line;
                continue;
            }

            if (false === fwrite(stream: $tmpFile, data: $lastLine)) {
                $file['error'] = UPLOAD_ERR_CANT_WRITE;
                fclose(stream: $tmpFile);
                continue;
            }

            $lastLine = $line;
        }

        fclose(stream: $stream);

        if ($files->count()) {
            $this->request->setFiles(files: $files);
        }

        $data->fromString(string: rtrim(string: $buffer, characters: '&'));

        return $data->toArray();
    }

    /**
     * Retrieve the part name from the content disposition, if present
     */
    protected function getNameFromHeaders(array $headers): false|string
    {
        $matches = [];
        if (!isset($headers['CONTENT-DISPOSITION'])) {
            return false;
        }

        if (!preg_match(
            pattern: '/(?:;|\s)name="(?P<name>[^"]+)"/',
            subject: (string) $headers['CONTENT-DISPOSITION'],
            matches: $matches,
        )) {
            return false;
        }

        return $matches['name'];
    }

    /**
     * Retrieve the filename from the content disposition, if present
     */
    protected function getFilenameFromHeaders(array $headers): false|string
    {
        $matches = [];
        if (!isset($headers['CONTENT-DISPOSITION'])) {
            return false;
        }

        if (!preg_match(
            pattern: '/filename="(?P<filename>[^"]*)"/',
            subject: (string) $headers['CONTENT-DISPOSITION'],
            matches: $matches,
        )) {
            return false;
        }

        return $matches['filename'];
    }

    /**
     * Retrieve the MIME type of the MIME part
     */
    protected function getMimeTypeFromHeaders(array $headers): string
    {
        if (!isset($headers['CONTENT-TYPE'])) {
            return 'application/octet-stream';
        }

        return Assert::string($headers['CONTENT-TYPE']);
    }
}
