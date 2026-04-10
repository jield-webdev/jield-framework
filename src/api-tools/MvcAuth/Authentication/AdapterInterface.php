<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authentication;

use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\Http\Request;
use Laminas\Http\Response;

interface AdapterInterface
{
    /**
     * @return array Array of types this adapter can handle.
     */
    public function provides(): array;

    /**
     * Attempt to match a requested authentication type
     * against what the adapter provides.
     *
     * @param string $type
     * @return bool
     */
    public function matches(string $type): bool;

    /**
     * Attempt to retrieve the authentication type based on the request.
     *
     * Allows an adapter to have custom logic for detecting if a request
     * might be providing credentials it's interested in.
     *
     */
    public function getTypeFromRequest(Request $request): false|string;

    /**
     * Perform pre-flight authentication operations.
     *
     * Use case would be for providing authentication challenge headers.
     *
     * @return void|Response
     */
    public function preAuth(Request $request, Response $response);

    /**
     * Attempt to authenticate the current request.
     *
     * @return IdentityInterface|\Laminas\Http\PhpEnvironment\Response
     */
    public function authenticate(Request $request, Response $response, MvcAuthEvent $mvcAuthEvent): IdentityInterface|\Laminas\Http\PhpEnvironment\Response;
}
