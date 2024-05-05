<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Enum;

enum HttpResponseEnum: int
{
    case OK = 200;
    case HTTP_UNAUTHORIZED = 401;
    case HTTP_FORBIDDEN = 403;
}
