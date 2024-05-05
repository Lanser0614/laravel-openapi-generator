<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Data;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Spatie\LaravelData\Data;

class Response extends Data
{
    public function __construct(
        public string $description,
        public Content $content,
    ) {
    }

    public static function fromRoute(ReflectionMethod|ReflectionFunction $method): self
    {
        $type = $method->getReturnType();

        if (! $type instanceof ReflectionNamedType) {
            throw new RuntimeException('Method does not have a return type');
        }

        return new self(
            description: $method->getName(),
            content: Content::fromReflection($type, $method),
        );
    }

    public static function unauthorized(ReflectionMethod|ReflectionFunction $method): self
    {
        return new self(
            description: 'Unauthorized',
            content: Content::fromClass(config('openapi-generator.error_scheme_class'), $method),
        );
    }

    public static function forbidden(ReflectionMethod|ReflectionFunction $method): self
    {
        return new self(
            description: 'Forbidden',
            content: Content::fromClass(config('openapi-generator.error_scheme_class'), $method),
        );
    }
}
