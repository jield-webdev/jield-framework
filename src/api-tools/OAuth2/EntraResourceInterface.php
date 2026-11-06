<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

interface EntraResourceInterface
{
    public function serviceIsMicrosoft(): bool;

    public function isEntraTokenExchangeEnabled(): bool;

    public function getTenantId(): ?string;

    public function getClientId(): string;

    public function getEntraAllowedClientIds(): ?string;
}
