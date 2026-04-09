<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Loader;

use Jield\I18n\Exception;
use Jield\I18n\Translator\Plural\Rule as PluralRule;
use Jield\I18n\Translator\TextDomain;

use function array_shift;
use function assert;
use function count;
use function explode;
use function fclose;
use function fopen;
use function fread;
use function fseek;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;
use function unpack;

/**
 * Loads translation files in gettext format
 */
final readonly class Gettext extends AbstractFileLoader
{
    /**
     * @throws Exception\InvalidArgumentException
     */
    public function load(string $locale, string $filename): TextDomain|null
    {
        $resolvedFile = $this->resolveFile(filename: $filename);
        if ($resolvedFile === false) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Could not find or open file %s for reading',
                $filename,
            ));
        }

        $textDomain = [];

        $file = fopen(filename: $resolvedFile, mode: 'rb');
        if ($file === false) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Could not open file %s for reading',
                $filename,
            ), code: 0);
        }

        $littleEndian = $this->isLittleEndian(file: $file, filename: $filename);
        $this->assertSupportedVersion(file: $file, littleEndian: $littleEndian, filename: $filename);

        // Gather main information
        $numStrings                   = $this->readInteger(file: $file, littleEndian: $littleEndian);
        $originalStringTableOffset    = $this->readInteger(file: $file, littleEndian: $littleEndian);
        $translationStringTableOffset = $this->readInteger(file: $file, littleEndian: $littleEndian);

        // Usually there follow size and offset of the hash table, but we have
        // no need for it, so we skip them.
        fseek(stream: $file, offset: $originalStringTableOffset);
        $originalStringTable = $this->readIntegerList(file: $file, littleEndian: $littleEndian, num: 2 * $numStrings);

        fseek(stream: $file, offset: $translationStringTableOffset);
        $translationStringTable = $this->readIntegerList(file: $file, littleEndian: $littleEndian, num: 2 * $numStrings);

        // Read in all translations
        for ($current = 0; $current < $numStrings; $current++) {
            $sizeKey                 = $current * 2 + 1;
            $offsetKey               = $current * 2 + 2;
            $originalStringSize      = $originalStringTable[$sizeKey];
            $originalStringOffset    = $originalStringTable[$offsetKey];
            $translationStringSize   = $translationStringTable[$sizeKey];
            $translationStringOffset = $translationStringTable[$offsetKey];

            assert(
                assertion: is_int(value: $originalStringSize)
                &&
                is_int(value: $originalStringOffset)
                &&
                is_int(value: $translationStringSize)
                &&
                is_int(value: $translationStringOffset),
            );

            $originalString = [''];
            if ($originalStringSize > 0) {
                fseek(stream: $file, offset: $originalStringOffset);
                $originalString = explode(separator: "\0", string: $this->read(file: $file, bytes: $originalStringSize));
            }

            if ($translationStringSize > 0) {
                fseek(stream: $file, offset: $translationStringOffset);
                $translationString = explode(separator: "\0", string: $this->read(file: $file, bytes: $translationStringSize));

                if (isset($originalString[1], $translationString[1])) {
                    $textDomain[$originalString[0]] = $translationString;

                    array_shift(array: $originalString);

                    foreach ($originalString as $string) {
                        if (! isset($textDomain[$string])) {
                            $textDomain[$string] = '';
                        }
                    }
                } else {
                    $textDomain[$originalString[0]] = $translationString[0];
                }
            }
        }

        fclose(stream: $file);

        $pluralRule = null;
        // Read header entries
        if (isset($textDomain['']) && is_string(value: $textDomain[''])) {
            $rawHeaders = explode(separator: "\n", string: trim(string: $textDomain['']));

            foreach ($rawHeaders as $rawHeader) {
                $data = explode(separator: ':', string: $rawHeader, limit: 2);
                assert(assertion: count(value: $data) === 2);
                [$header, $content] = $data;
                if (strtolower(string: trim(string: $header)) === 'plural-forms') {
                    $pluralRule = $content;
                }
            }

            unset($textDomain['']);
        }

        $textDomain = new TextDomain(array: $textDomain);
        if (is_string(value: $pluralRule) && $pluralRule !== '') {
            $textDomain->setPluralRule(rule: PluralRule::fromString(string: $pluralRule));
        }

        return $textDomain;
    }

    /**
     * Read a single integer from the current file.
     *
     * @param resource $file
     */
    private function readInteger($file, bool $littleEndian): int
    {
        $value = $this->read(file: $file, bytes: 4);

        if ($littleEndian) {
            $result = unpack(format: 'Vint', string: $value);
        } else {
            $result = unpack(format: 'Nint', string: $value);
        }

        assert(assertion: is_array(value: $result));

        $integer = $result['int'] ?? null;

        assert(assertion: is_int(value: $integer));

        return $integer;
    }

    /**
     * Read an integer from the current file.
     *
     * @param resource $file
     */
    private function readIntegerList($file, bool $littleEndian, int $num): array
    {
        $value = $this->read(file: $file, bytes: 4 * $num);

        $result = $littleEndian
            ? unpack(format: 'V' . $num, string: $value)
            : unpack(format: 'N' . $num, string: $value);

        assert(assertion: is_array(value: $result));

        return $result;
    }

    /** @param resource $file */
    private function read($file, int $bytes): string
    {
        $content = fread(stream: $file, length: $bytes);
        assert(assertion: $content !== false);

        return $content;
    }

    /**
     * @param resource $file
     * @throws Exception\InvalidArgumentException
     */
    private function isLittleEndian($file, string $filename): bool
    {
        // Verify magic number
        $magic = $this->read(file: $file, bytes: 4);

        if ($magic === "\x95\x04\x12\xde") {
            return false;
        }

        if ($magic === "\xde\x12\x04\x95") {
            return true;
        }

        fclose(stream: $file);
        throw new Exception\InvalidArgumentException(message: sprintf(
            '%s is not a valid gettext file',
            $filename
        ));
    }

    /**
     * Verify major revision (only 0 and 1 supported)
     *
     * @param resource $file
     * @throws Exception\InvalidArgumentException
     */
    private function assertSupportedVersion($file, bool $littleEndian, string $filename): void
    {
        $majorRevision = $this->readInteger(file: $file, littleEndian: $littleEndian) >> 16;

        if ($majorRevision === 0 || $majorRevision === 1) {
            return;
        }

        fclose(stream: $file);
        throw new Exception\InvalidArgumentException(message: sprintf(
            '%s has an unknown major revision',
            $filename,
        ));
    }
}
