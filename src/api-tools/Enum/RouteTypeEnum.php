<?php

declare(strict_types=1);

namespace Jield\ApiTools\Enum;

enum RouteTypeEnum: string
{
    case ENTITY = 'entity';
    case COLLECTION = 'collection';
}
