<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

use SensitiveParameter;

final readonly class IssuedAccessToken
{
    public function __construct(
        #[SensitiveParameter]
        public string $accessToken,
        public int $issuedAt,
        public int $expiresAt,
        public string $scope,
    ) {
    }

    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'issued_token_type' => TokenExchange::ACCESS_TOKEN,
            'token_type' => 'Bearer',
            'expires_in' => $this->expiresAt - $this->issuedAt,
            'scope' => $this->scope,
        ];
    }
}
