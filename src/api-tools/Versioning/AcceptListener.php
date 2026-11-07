<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning;

use Override;

use function array_reverse;
use function array_shift;
use function explode;
use function preg_match;

class AcceptListener extends ContentTypeListener
{
    /**
     * Header to examine.
     */
    protected string $headerName = 'accept';

    /**
     * Parse the header for matches against registered regexes
     */
    #[Override]
    protected function parseHeaderForMatches(string $value): false|array
    {
        // Accept header is made up of media ranges
        $matches = [];
        $mediaRanges = explode(separator: ',', string: $value);

        foreach ($mediaRanges as $mediaRange) {
            // Media range consists of mediatype and parameters
            $params = explode(separator: ';', string: $mediaRange);
            $mediaType = array_shift(array: $params);

            /** @mago-var mixed $regex */
            foreach (array_reverse(array: $this->regexes) as $regex) {
                if (
                    !preg_match(
                        pattern: \Webmozart\Assert\Assert::string($regex),
                        subject: $mediaType,
                        matches: $matches,
                    )
                ) {
                    continue;
                }

                return $matches;
            }
        }

        return false;
    }
}
