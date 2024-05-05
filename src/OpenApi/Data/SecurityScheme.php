<?php

namespace Lanser\LaravelApiGenerator\OpenApi\Data;

use Illuminate\Routing\Route;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;
use Spatie\LaravelData\Support\Wrapping\WrapExecutionType;

class SecurityScheme extends Data
{
    public const BEARER_SECURITY_SCHEME = 'bearer';

    public function __construct(
        protected string $scheme,
        /** @var string[] */
        public array $permissions = [],
    ) {
    }

    /**
     * @param Route $route
     * @return null|DataCollection<int,static>
     */
    public static function fromRoute(Route $route): ?DataCollection
    {
        $security    = [];
        $permissions = static::getPermissions($route);

        /** @var string[] $middlewares */
        $middlewares = $route->middleware();

        if (in_array('auth:sanctum', $middlewares)) {
            $security[] = new self(
                scheme: self::BEARER_SECURITY_SCHEME,
                permissions: $permissions,
            );
        }

        if (0 === count($security)) {
            return null;
        }

        return self::collect($security);
    }

    /**
     * @return string[]
     */
    public static function getPermissions(Route $route): array
    {
        /** @var string[] $permissions */
        $permissions = [];

        /** @var string[] $middlewares */
        $middlewares = $route->middleware();

        foreach ($middlewares as $middleware) {
            if (str_starts_with($middleware, 'can:')) {
                $permissions[] = self::strAfter($middleware, 'can:');
            }
        }

        return $permissions;
    }

    protected static function strAfter(string $subject, string $search): string
    {
        return '' === $search ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    public function transform(
        bool|TransformationContextFactory|TransformationContext|null $transformationContext = null,
        WrapExecutionType                                            $wrapExecutionType = WrapExecutionType::Disabled,
        bool                                                         $mapPropertyNames = true,
    ): array {
        return [$this->scheme => $this->permissions];
    }
}
