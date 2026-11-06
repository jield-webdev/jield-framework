<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

interface EntraClientInterface
{
    public function serviceIsMicrosoft(): bool;

    public function getTenantId(): ?string;

    public function getClientId(): string;

    public function getClientSecret(): string;

    public function getAccessTokenUrl(): ?string;
}
