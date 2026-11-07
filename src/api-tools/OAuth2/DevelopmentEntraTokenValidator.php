<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

final class DevelopmentEntraTokenValidator extends EntraTokenValidator
{
    protected function invalid(string $standardMessage, string $detail): never
    {
        throw new EntraException('invalid_grant', $standardMessage . ': ' . $detail);
    }

    protected function signingKeyFailure(EntraException $exception, string $kid): never
    {
        throw new EntraException(
            $exception->error,
            $exception->getMessage()
            . ': signing key lookup failed for kid '
            . (string) json_encode($kid, JSON_INVALID_UTF8_SUBSTITUTE),
            $exception->status,
        );
    }
}
