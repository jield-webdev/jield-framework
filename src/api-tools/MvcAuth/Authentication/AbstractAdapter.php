<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authentication;

use Laminas\Http\Request;
use Override;
use function in_array;
use function preg_split;
use function strtolower;
use function trim;

abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * Authorization token types this adapter can fulfill.
     *
     * @var array
     */
    protected array $authorizationTokenTypes = [];

    /**
     * Determine if the incoming request provides either basic or digest
     * credentials
     *
     */
    #[Override]
    public function getTypeFromRequest(Request $request): false|string
    {
        $request->getHeaders();
        $authorization = $request->getHeader(name: 'Authorization');
        if (!$authorization) {
            return false;
        }

        $authorization = trim(string: $authorization->getFieldValue());
        $type          = $this->getTypeFromAuthorizationHeader(header: $authorization);

        if (!in_array(needle: $type, haystack: $this->authorizationTokenTypes)) {
            return false;
        }

        return $type;
    }

    /**
     * Determine the authentication type from the authorization header contents
     *
     * @param string $header
     * @return false|string
     */
    private function getTypeFromAuthorizationHeader(string $header): false|string
    {
        // we only support headers in the format: Authorization: xxx yyyyy
        if (!str_contains(haystack: $header, needle: ' ')) {
            return false;
        }

        [$type, $credential] = preg_split(pattern: '# #', subject: $header, limit: 2);

        return strtolower(string: $type);
    }
}
