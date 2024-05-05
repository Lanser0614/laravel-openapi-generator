<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Data;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Spatie\LaravelData\Data;

class ErrorResponse extends Data
{
    public function __construct(
        public string $description,
        public string $content,
    ) {
    }

    public static function fromRoute(string $method, string $message): self
    {


        return new self(
            description: $method,
            content:  Content::fromReflection('application/json', $message),
        );
    }
}
