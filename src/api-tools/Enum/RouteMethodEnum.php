<?php

declare(strict_types=1);

namespace Jield\ApiTools\Enum;

enum RouteMethodEnum: string
{
    case GET = 'GET';
    case POST = 'POST';
    case DELETE = 'DELETE';
    case PATCH = 'PATCH';
}
